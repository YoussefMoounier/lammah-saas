<?php

use App\Http\Controllers\Api\V1\BulkPriceUpdateController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\FraudSignalController;
use App\Http\Controllers\Api\V1\NetProfitController;
use App\Http\Controllers\Api\V1\RfmScoreController;
use App\Http\Controllers\Api\V1\StaffShiftController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Route::post('/webhooks/woocommerce/{store}', WooCommerceWebhookController::class)
    ->name('webhooks.woocommerce.store');

/*
|--------------------------------------------------------------------------
| Temporary migration route
|--------------------------------------------------------------------------
*/

Route::get('/ops/run-migrations/{secret}', function (string $secret) {
    $expectedSecret = getenv('BOOTSTRAP_SECRET') ?: env('BOOTSTRAP_SECRET');

    if (!$expectedSecret || !hash_equals($expectedSecret, $secret)) {
        return response()->json([
            'ok' => false,
            'stage' => 'invalid_secret',
            'message' => 'Invalid bootstrap secret.',
        ], 403);
    }

    try {
        Artisan::call('migrate', [
            '--force' => true,
        ]);

        return response()->json([
            'ok' => true,
            'stage' => 'migrations_done',
            'output' => Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'stage' => 'migration_exception',
            'error_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});

/*
|--------------------------------------------------------------------------
| Temporary merchant + store seed route
|--------------------------------------------------------------------------
*/

Route::get('/ops/seed-merchant-store/{secret}', function (string $secret) {
    $expectedSecret = getenv('BOOTSTRAP_SECRET') ?: env('BOOTSTRAP_SECRET');

    if (!$expectedSecret || !hash_equals($expectedSecret, $secret)) {
        return response()->json([
            'ok' => false,
            'stage' => 'invalid_secret',
            'message' => 'Invalid bootstrap secret.',
        ], 403);
    }

    try {
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
        | Create or get bootstrap user
        |--------------------------------------------------------------------------
        */

        $user = User::firstOrCreate(
            ['email' => $env('BOOTSTRAP_ADMIN_EMAIL', 'admin@lammah.local')],
            [
                'name' => $env('BOOTSTRAP_ADMIN_NAME', 'Admin'),
                'password' => Hash::make($env('BOOTSTRAP_ADMIN_PASSWORD', 'change-this-password')),
            ]
        );

        $merchantColumns = Schema::connection('pgsql')->getColumnListing('merchants');
        $storeColumns = Schema::connection('pgsql')->getColumnListing('woocommerce_stores');
        $pivotColumns = Schema::connection('pgsql')->getColumnListing('merchant_user');

        /*
        |--------------------------------------------------------------------------
        | Create Merchant if missing
        |--------------------------------------------------------------------------
        */

        $merchant = DB::table('merchants')->first();

        if (!$merchant) {
            $merchantId = (string) Str::ulid();

            $merchantInsert = [];

            if (in_array('id', $merchantColumns, true)) {
                $merchantInsert['id'] = $merchantId;
            }

            if (in_array('name', $merchantColumns, true)) {
                $merchantInsert['name'] = 'Lammah Test Merchant';
            }

            if (in_array('company_name', $merchantColumns, true)) {
                $merchantInsert['company_name'] = 'Lammah Test Company';
            }

            if (in_array('slug', $merchantColumns, true)) {
                $merchantInsert['slug'] = 'lammah-test-merchant';
            }

            if (in_array('email', $merchantColumns, true)) {
                $merchantInsert['email'] = $user->email;
            }

            if (in_array('contact_name', $merchantColumns, true)) {
                $merchantInsert['contact_name'] = $user->name ?? 'Admin';
            }

            if (in_array('phone', $merchantColumns, true)) {
                $merchantInsert['phone'] = '+201000000000';
            }

            if (in_array('country', $merchantColumns, true)) {
                $merchantInsert['country'] = 'EG';
            }

            if (in_array('currency', $merchantColumns, true)) {
                $merchantInsert['currency'] = 'SAR';
            }

            if (in_array('timezone', $merchantColumns, true)) {
                $merchantInsert['timezone'] = 'UTC';
            }

            if (in_array('user_id', $merchantColumns, true)) {
                $merchantInsert['user_id'] = $user->id;
            }

            if (in_array('owner_id', $merchantColumns, true)) {
                $merchantInsert['owner_id'] = $user->id;
            }

            if (in_array('owner_user_id', $merchantColumns, true)) {
                $merchantInsert['owner_user_id'] = $user->id;
            }

            if (in_array('status', $merchantColumns, true)) {
                $merchantInsert['status'] = 'active';
            }

            if (in_array('metadata', $merchantColumns, true)) {
                $merchantInsert['metadata'] = json_encode([
                    'created_by' => 'bootstrap_route',
                ]);
            }

            if (in_array('created_at', $merchantColumns, true)) {
                $merchantInsert['created_at'] = now();
            }

            if (in_array('updated_at', $merchantColumns, true)) {
                $merchantInsert['updated_at'] = now();
            }

            DB::table('merchants')->insert($merchantInsert);

            $merchant = DB::table('merchants')->where('id', $merchantId)->first();
        }

        /*
        |--------------------------------------------------------------------------
        | Link User to Merchant
        |--------------------------------------------------------------------------
        */

        if (
            in_array('merchant_id', $pivotColumns, true) &&
            in_array('user_id', $pivotColumns, true)
        ) {
            $exists = DB::table('merchant_user')
                ->where('merchant_id', $merchant->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$exists) {
                $pivotInsert = [
                    'merchant_id' => $merchant->id,
                    'user_id' => $user->id,
                ];

                if (in_array('role', $pivotColumns, true)) {
                    $pivotInsert['role'] = 'owner';
                }

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
        | Create WooCommerce Store if missing
        |--------------------------------------------------------------------------
        */

        $storeQuery = DB::table('woocommerce_stores');

        if (in_array('merchant_id', $storeColumns, true)) {
            $storeQuery->where('merchant_id', $merchant->id);
        }

        $store = $storeQuery->first();

        if (!$store) {
            $storeId = (string) Str::ulid();

            $baseUrl = 'https://example.com';
            $consumerKey = 'ck_demo';
            $consumerSecret = 'cs_demo';
            $webhookSecret = 'demo_webhook_secret';

            $storeInsert = [
                'id' => $storeId,
                'merchant_id' => $merchant->id,
                'name' => 'Demo WooCommerce Store',
                'base_url' => $baseUrl,
                'base_url_hash' => hash('sha256', $baseUrl),
                'consumer_key_encrypted' => Crypt::encryptString($consumerKey),
                'consumer_secret_encrypted' => Crypt::encryptString($consumerSecret),
                'webhook_secret_encrypted' => Crypt::encryptString($webhookSecret),
                'api_version' => 'wc/v3',
                'currency' => 'SAR',
                'timezone' => 'UTC',
                'status' => 'active',
                'sync_settings' => json_encode([]),
                'metadata' => json_encode([
                    'created_by' => 'bootstrap_route',
                    'note' => 'temporary demo store',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            /*
            |--------------------------------------------------------------------------
            | Safety: only keep columns that actually exist
            |--------------------------------------------------------------------------
            */

            $storeInsert = array_filter(
                $storeInsert,
                fn ($value, $key) => in_array($key, $storeColumns, true),
                ARRAY_FILTER_USE_BOTH
            );

            DB::table('woocommerce_stores')->insert($storeInsert);

            $store = DB::table('woocommerce_stores')
                ->where('id', $storeId)
                ->first();
        }

        return response()->json([
            'ok' => true,
            'stage' => 'merchant_store_seeded',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name ?? null,
                'company_name' => $merchant->company_name ?? null,
            ],
            'store' => [
                'id' => $store->id,
                'merchant_id' => $store->merchant_id ?? null,
                'name' => $store->name ?? null,
                'base_url' => $store->base_url ?? null,
                'base_url_hash' => $store->base_url_hash ?? null,
            ],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'stage' => 'seed_exception',
            'error_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});

/*
|--------------------------------------------------------------------------
| Temporary dashboard bootstrap route
|--------------------------------------------------------------------------
*/

Route::get('/ops/bootstrap-dashboard/{secret}', function (string $secret) {
    $expectedSecret = getenv('BOOTSTRAP_SECRET') ?: env('BOOTSTRAP_SECRET');

    if (!$expectedSecret || !hash_equals($expectedSecret, $secret)) {
        return response()->json([
            'ok' => false,
            'stage' => 'invalid_secret',
            'message' => 'Invalid bootstrap secret.',
        ], 403);
    }

    try {
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
                'message' => 'Required tables are missing in Supabase. Migrations probably have not been run on Supabase.',
                'missing_tables' => $missingTables,
                'db_info' => $dbInfo,
            ], 500);
        }

        $user = User::firstOrCreate(
            ['email' => $env('BOOTSTRAP_ADMIN_EMAIL', 'admin@lammah.local')],
            [
                'name' => $env('BOOTSTRAP_ADMIN_NAME', 'Admin'),
                'password' => Hash::make($env('BOOTSTRAP_ADMIN_PASSWORD', 'change-this-password')),
            ]
        );

        $merchant = DB::table('merchants')->first();

        if (!$merchant) {
            return response()->json([
                'ok' => false,
                'stage' => 'no_merchant',
                'message' => 'Table merchants exists but is empty. Create/connect a merchant first.',
                'db_info' => $dbInfo,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ], 404);
        }

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
                'message' => 'Table woocommerce_stores exists but has no store for this merchant.',
                'merchant_ulid' => $merchant->id,
                'db_info' => $dbInfo,
            ], 404);
        }

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

                if (in_array('role', $pivotColumns, true)) {
                    $pivotInsert['role'] = 'owner';
                }

                if (in_array('created_at', $pivotColumns, true)) {
                    $pivotInsert['created_at'] = now();
                }

                if (in_array('updated_at', $pivotColumns, true)) {
                    $pivotInsert['updated_at'] = now();
                }

                DB::table('merchant_user')->insert($pivotInsert);
            }
        }

        $token = $user
            ->createToken('dashboard-token-' . now()->format('YmdHis'), ['*'])
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

            'records' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
                'merchant' => [
                    'id' => $merchant->id,
                    'name' => $merchant->name ?? null,
                    'company_name' => $merchant->company_name ?? null,
                ],
                'store' => [
                    'id' => $store->id,
                    'merchant_id' => $store->merchant_id ?? null,
                    'name' => $store->name ?? null,
                    'base_url' => $store->base_url ?? null,
                    'base_url_hash' => $store->base_url_hash ?? null,
                ],
            ],

            'security_warning' => 'Copy the values, then delete this route or change BOOTSTRAP_SECRET immediately.',
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

/*
|--------------------------------------------------------------------------
| Protected API v1 routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/merchants/{merchant}/dashboard/summary', DashboardSummaryController::class)
            ->name('api.v1.dashboard.summary');

        Route::get('/merchants/{merchant}/stores/{store}/forecasts', [ForecastController::class, 'index'])
            ->name('api.v1.forecasts.index');

        Route::post('/merchants/{merchant}/stores/{store}/forecasts/generate', [ForecastController::class, 'generate'])
            ->name('api.v1.forecasts.generate');

        Route::get('/merchants/{merchant}/stores/{store}/fraud-signals', [FraudSignalController::class, 'index'])
            ->name('api.v1.fraud-signals.index');

        Route::post('/merchants/{merchant}/stores/{store}/fraud-signals/scan', [FraudSignalController::class, 'scan'])
            ->name('api.v1.fraud-signals.scan');

        Route::patch('/merchants/{merchant}/fraud-signals/{signal}', [FraudSignalController::class, 'update'])
            ->name('api.v1.fraud-signals.update');

        Route::get('/merchants/{merchant}/stores/{store}/rfm-scores', [RfmScoreController::class, 'index'])
            ->name('api.v1.rfm-scores.index');

        Route::post('/merchants/{merchant}/stores/{store}/rfm-scores/refresh', [RfmScoreController::class, 'refresh'])
            ->name('api.v1.rfm-scores.refresh');

        Route::get('/merchants/{merchant}/stores/{store}/profits', [NetProfitController::class, 'index'])
            ->name('api.v1.profits.index');

        Route::post('/merchants/{merchant}/orders/{order}/profit/recalculate', [NetProfitController::class, 'recalculate'])
            ->name('api.v1.profits.recalculate');

        Route::get('/merchants/{merchant}/shifts', [StaffShiftController::class, 'index'])
            ->name('api.v1.shifts.index');

        Route::post('/merchants/{merchant}/shifts/start', [StaffShiftController::class, 'start'])
            ->name('api.v1.shifts.start');

        Route::patch('/merchants/{merchant}/shifts/{shift}/close', [StaffShiftController::class, 'close'])
            ->name('api.v1.shifts.close');

        Route::get('/merchants/{merchant}/stores/{store}/price-updates', [BulkPriceUpdateController::class, 'index'])
            ->name('api.v1.price-updates.index');

        Route::post('/merchants/{merchant}/stores/{store}/price-updates', [BulkPriceUpdateController::class, 'store'])
            ->name('api.v1.price-updates.store');

        Route::get('/merchants/{merchant}/price-updates/{batch}', [BulkPriceUpdateController::class, 'show'])
            ->name('api.v1.price-updates.show');
    });
