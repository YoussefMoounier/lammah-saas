<?php

namespace App\Services\Analytics;

use App\Models\SalesForecast;
use App\Models\WooCommerceStore;
use App\Repositories\Analytics\AnalyticsRepository;
use App\Services\Analytics\Statistics\LinearRegression;
use Throwable;

class SalesForecastingService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly LinearRegression $linearRegression,
    ) {
    }

    public function forecastNextMonth(WooCommerceStore $store, int $trainingMonths = 6): SalesForecast
    {
        $trainingMonths = max(2, $trainingMonths);
        $series = $this->repository->monthlySalesSeries($store, $trainingMonths);
        $windowStart = now()->startOfMonth()->subMonths($trainingMonths - 1);
        $windowEnd = now()->endOfMonth();
        $run = $this->repository->startMetricRun(
            merchantId: $store->merchant_id,
            storeId: $store->id,
            metricType: 'sales_forecast',
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            parameters: ['training_months' => $trainingMonths],
        );

        try {
            $grossModel = $this->linearRegression->fit($this->points($series, 'gross_revenue'));
            $netModel = $this->linearRegression->fit($this->points($series, 'net_profit'));
            $orderModel = $this->linearRegression->fit($this->points($series, 'order_count'));
            $nextX = count($series) + 1;
            $forecastMonth = now()->startOfMonth()->addMonth()->toDateString();
            $grossForecast = max(0, $grossModel->predict($nextX));
            $netForecast = max(0, $netModel->predict($nextX));
            $orderForecast = max(0, (int) round($orderModel->predict($nextX)));
            $confidencePadding = 1.96 * $grossModel->standardError;

            $forecast = $this->repository->saveSalesForecast($store, $run, [
                'forecast_month' => $forecastMonth,
                'training_months' => $trainingMonths,
                'gross_revenue_forecast' => $this->money($grossForecast),
                'net_profit_forecast' => $this->money($netForecast),
                'order_count_forecast' => $orderForecast,
                'confidence_low' => $this->money(max(0, $grossForecast - $confidencePadding)),
                'confidence_high' => $this->money($grossForecast + $confidencePadding),
                'model_coefficients' => [
                    'gross_revenue' => $grossModel->toArray(),
                    'net_profit' => $netModel->toArray(),
                    'order_count' => $orderModel->toArray(),
                    'series' => $series,
                ],
                'source_window_start' => $windowStart->toDateString(),
                'source_window_end' => $windowEnd->toDateString(),
            ]);

            $this->repository->finishMetricRun($run, [
                'forecast_month' => $forecastMonth,
                'gross_revenue_forecast' => $forecast->gross_revenue_forecast,
                'order_count_forecast' => $forecast->order_count_forecast,
            ]);

            return $forecast;
        } catch (Throwable $exception) {
            $this->repository->failMetricRun($run, $exception->getMessage());

            throw $exception;
        }
    }

    private function points(array $series, string $key): array
    {
        return array_map(fn (array $row): array => [
            'x' => (float) $row['x'],
            'y' => (float) $row[$key],
        ], $series);
    }

    private function money(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
