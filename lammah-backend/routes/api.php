<?php

use App\Http\Controllers\Api\V1\BulkPriceUpdateController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\FraudSignalController;
use App\Http\Controllers\Api\V1\NetProfitController;
use App\Http\Controllers\Api\V1\RfmScoreController;
use App\Http\Controllers\Api\V1\StaffShiftController;
use App\Http\Controllers\Webhooks\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/woocommerce/{store}', WooCommerceWebhookController::class)
    ->name('webhooks.woocommerce.store');

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
Route::get('/generate-token-safely', function () {
    // 1. تنظيف الجدول تماماً
    DB::table('personal_access_tokens')->truncate();

    // 2. هنجيب اليوزر
    $user = App\Models\User::where('email', 'youssef_dynamic@lammah.saas')->first();
    if (!$user) {
        return "User not found!";
    }

    // 3. توليد التوكن الصافي والـ Hash بتاعه والـ ULID يدوي بالمللي
    $rawToken = Str::random(40);
    $hashedToken = hash('sha256', $rawToken);
    $ulid = (string) Str::ulid();

    // 4. حشر البيانات جوه ريلواي بالعافية مع الـ ULID
    DB::table('personal_access_tokens')->insert([
        'id' => $ulid,
        'tokenable_type' => 'App\Models\User',
        'tokenable_id' => $user->id,
        'name' => 'web-console',
        'token' => $hashedToken,
        'abilities' => json_encode(['*']),
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // 5. التكة الفاجرة: دمج الـ ID الفعلي للتوكن مع الـ Raw token بالـ pipe |
    // لارافيل لما يجيله الطلب هياخد الجزء التاني يعمله Hash ويطابقه، والـ Next.js هتعتبره صالح!
    $finalSanctumToken = $ulid . '|' . $rawToken;

    return response()->json([
        'SANCTUM_TOKEN' => $finalSanctumToken,
        'MERCHANT_ULID' => $user->id
    ]);
});