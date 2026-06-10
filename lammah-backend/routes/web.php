<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Route::get('/ops/bootstrap-dashboard/{secret}', function (string $secret) {

    /*
    |--------------------------------------------------------------------------
    | حماية الرابط
    |--------------------------------------------------------------------------
    */

    $expectedSecret = getenv('BOOTSTRAP_SECRET') ?: env('BOOTSTRAP_SECRET');

    if (!$expectedSecret || !hash_equals($expectedSecret, $secret)) {
        abort(403, 'Invalid bootstrap secret.');
    }

    try {
        /*
        |--------------------------------------------------------------------------
        | إجبار Laravel يستخدم pgsql
        |--------------------------------------------------------------------------
        */

        $env = function (string $key, mixed $default = null) {
            $value = getenv($key);

            if ($value !== false && $value !== '') {
                return $value;
            }

            return env($key, $default);
        };

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
        | اختبار الاتصال
        |--------------------------------------------------------------------------
        */

        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return response()->json([
                'ok' => false,
                'error' => 'Laravel is not using pgsql.',
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
        | التأكد من الجداول
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
                'message' => 'الجداول دي مش موجودة في Supabase. غالبًا migrations ما اتعملتش.',
                'missing_tables' => $missingTables,
                'db_info' => $dbInfo,
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | إنشاء أو جلب User
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
        | جلب Merchant ULID الصحيح من merchants.id
        |--------------------------------------------------------------------------
        */

        $merchant = DB::table('merchants')
            ->orderBy('created_at')
            ->first();

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
        | جلب Store ULID الصحيح من woocommerce_stores.id
        |--------------------------------------------------------------------------
        */

        $store = DB::table('woocommerce_stores')
            ->where('merchant_id', $merchant->id)
            ->orderBy('created_at')
            ->first();

        if (!$store) {
            return response()->json([
                'ok' => false,
                'stage' => 'no_store',
                'message' => 'جدول woocommerce_stores موجود لكنه مفيهوش Store لهذا الـ Merchant.',
                'merchant_ulid' => $merchant->id,
                'db_info' => $dbInfo,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | إنشاء Sanctum Token
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken('dashboard-token-' . now()->format('YmdHis'))
            ->plainTextToken;

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
});
