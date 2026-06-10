<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Route::get('/', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Laravel backend is running',
    ]);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
]);

Route::get('/ops/bootstrap-dashboard/{secret}', function (string $secret) {

    /*
    |--------------------------------------------------------------------------
    | 1) حماية الرابط
    |--------------------------------------------------------------------------
    */

    $expectedSecret = getenv('BOOTSTRAP_SECRET') ?: env('BOOTSTRAP_SECRET');

    if (!$expectedSecret || !hash_equals($expectedSecret, $secret)) {
        return response()->json([
            'ok' => false,
            'stage' => 'invalid_secret',
            'message' => 'Invalid bootstrap secret.',
        ], 403);
    }

    try {
        /*
        |--------------------------------------------------------------------------
        | 2) قراءة Environment Variables من Railway
        |--------------------------------------------------------------------------
        */

        $env = function (string $key, mixed $default = null) {
            $value = getenv($key);

            if ($value !== false && $value !== '') {
                return $value;
            }

            return env($key, $default);
        };

        /*
        |--------------------------------------------------------------------------
        | 3) إجبار Laravel يستخدم pgsql / Supabase
        |--------------------------------------------------------------------------
        */

        config([
            'database.default' => 'pgsql',

            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.host' => $env('DB_HOST'),
            'database.connections.pgsql.port' => $env('DB_PORT', 5432),
            'database.connections.pgsql.database' => $env('DB_DATABASE', 'postgres'),
            'database.connections.pgsql.username' => $env('DB_USERNAME'),
            'database.connections.pgsql.password' => $env('DB_PASSWORD'),
            'database.connections.pgsql.charset' => 'utf8',
            'database.connections.pgsql.prefix' => '',
            'database.connections.pgsql.prefix_indexes' => true,
            'database.connections.pgsql.search_path' => $env('DB_SCHEMA', 'public'),
            'database.connections.pgsql.sslmode' => $env('DB_SSLMODE', 'require'),
        ]);

        DB::purge('pgsql');
        DB::reconnect('pgsql');

        /*
        |--------------------------------------------------------------------------
        | 4) اختبار الاتصال بقاعدة البيانات
        |--------------------------------------------------------------------------
        */

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return response()->json([
                'ok' => false,
                'stage' => 'driver_check',
                'message' => 'Laravel is not using pgsql.',
                'detected_driver' => $driver,
            ], 500);
        }

        $dbInfo = DB::selectOne("
            select
                current_database() as database_name,
                current_user as database_user,
                version() as postgres_version
        ");

        /*
        |--------------------------------------------------------------------------
        | 5) التأكد من وجود الجداول المطلوبة
        |--------------------------------------------------------------------------
        */

        $requiredTables = [
            'users',
            'merchants',
            'merchant_user',
            'woocommerce_stores',
            'personal_access_tokens',
        ];

        $missingTables = [];

        foreach ($requiredTables as $table) {
            if (!Schema::connection('pgsql')->hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            return response()->json([
                'ok' => false,
                'stage' => 'missing_tables',
                'message' => 'الجداول دي مش موجودة في Supabase. غالبًا migrations ما اتعملتش على Supabase.',
                'missing_tables' => $missingTables,
                'db_info' => $dbInfo,
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | 6) إنشاء أو جلب User
        |--------------------------------------------------------------------------
        */

        $user = User::firstOrCreate(
            ['email' => $env('BOOTSTRAP_ADMIN_EMAIL', 'admin@lammah.local')],
            [
                'name' => $env('BOOTSTRAP_ADMIN_NAME', 'Admin'),
                'password' => Hash::make($env('BOOTSTRAP_ADMIN_PASSWORD', 'change-this-password')),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 7) جلب Merchant ULID الصحيح من جدول merchants
        |--------------------------------------------------------------------------
        */

        $merchant = DB::table('merchants')->first();

        if (!$merchant) {
            return response()->json([
                'ok' => false,
                'stage' => 'no_merchant',
                'message' => 'جدول merchants موجود لكنه فاضي. لازم يكون فيه Merchant الأول.',
                'db_info' => $dbInfo,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 8) جلب Store ULID الصحيح من جدول woocommerce_stores
        |--------------------------------------------------------------------------
        */

        $storeColumns = Schema::connection('pgsql')->getColumnListing('woocommerce_stores');

        $storeQuery = DB::table('woocommerce_stores');

        if (in_array('merchant_id', $storeColumns, true)) {
            $storeQuery->where('merchant_id', $merchant->id);
        }

        $store = $storeQuery->first();

        if (!$store) {
            return response()->json([
                'ok' => false,
                'stage' => 'no_store',
                'message' => 'جدول woocommerce_stores موجود لكنه فاضي أو مفيهوش Store مربوط بهذا الـ Merchant.',
                'merchant_ulid' => $merchant->id,
                'db_info' => $dbInfo,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 9) ربط المستخدم بالـ Merchant داخل merchant_user لو الأعمدة موجودة
        |--------------------------------------------------------------------------
        */

        $pivotColumns = Schema::connection('pgsql')->getColumnListing('merchant_user');

        if (
            in_array('merchant_id', $pivotColumns, true) &&
            in_array('user_id', $pivotColumns, true)
        ) {
            $alreadyLinked = DB::table('merchant_user')
                ->where('merchant_id', $merchant->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$alreadyLinked) {
                $pivotInsert = [
                    'merchant_id' => $merchant->id,
                    'user_id' => $user->id,
                ];

                if (in_array('created_at', $pivotColumns, true)) {
                    $pivotInsert['created_at'] = now();
                }

                if (in_array('updated_at', $pivotColumns, true)) {
                    $pivotInsert['updated_at'] = now();
                }

                DB::table('merchant_user')->insert($pivotInsert);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 10) إنشاء Sanctum Token
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken('dashboard-token-' . now()->format('YmdHis'))
            ->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | 11) إخراج البيانات المطلوبة للـ Frontend
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'ok' => true,

            'copy_to_frontend' => [
                'API_BASE_URL' => $env('APP_URL') ?: request()->getSchemeAndHttpHost(),
                'SANCTUM_TOKEN' => $token,
                'MERCHANT_ULID' => $merchant->id,
                'STORE_ULID' => $store->id,
            ],

            'verification' => [
                'database_driver' => $driver,
                'database_info' => $dbInfo,
                'merchant_ulid_source' => 'merchants.id',
                'store_ulid_source' => 'woocommerce_stores.id',
                'important_note' => 'MERCHANT_ULID is not user id.',
            ],

            'records' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
                'merchant' => [
                    'id' => $merchant->id,
                    'name' => $merchant->name ?? null,
                ],
                'store' => [
                    'id' => $store->id,
                    'merchant_id' => $store->merchant_id ?? null,
                    'name' => $store->name ?? null,
                    'base_url' => $store->base_url ?? null,
                ],
            ],

            'security_warning' => 'انسخ القيم المطلوبة ثم احذف هذا الـ Route أو غيّر BOOTSTRAP_SECRET فورًا.',
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'stage' => 'exception',
            'error_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
]);
