<?php

namespace App\Services\Analytics;

use App\Models\RfmCustomerScore;
use App\Models\WooCommerceStore;
use App\Repositories\Analytics\AnalyticsRepository;
use App\Services\Analytics\Scoring\RfmScorer;
use Illuminate\Support\Carbon;
use Throwable;

class RfmChurnAnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly RfmScorer $scorer,
    ) {
    }

    /**
     * @return array<int, RfmCustomerScore>
     */
    public function scoreStore(WooCommerceStore $store, int $lookbackDays = 180): array
    {
        $since = now()->subDays($lookbackDays);
        $run = $this->repository->startMetricRun(
            merchantId: $store->merchant_id,
            storeId: $store->id,
            metricType: 'rfm_churn',
            windowStart: $since,
            windowEnd: now(),
            parameters: ['lookback_days' => $lookbackDays],
        );

        try {
            $customers = $this->repository->rfmRows($store, $since);
            $recencyDistribution = [];
            $frequencyDistribution = [];
            $monetaryDistribution = [];
            $prepared = [];

            foreach ($customers as $customer) {
                $lastOrderAt = Carbon::parse($customer->last_order_at);
                $row = [
                    'customer' => $customer,
                    'recency_days' => $lastOrderAt->diffInDays(now()),
                    'frequency_orders' => (int) $customer->frequency_orders,
                    'monetary_value' => (float) ($customer->monetary_value ?? 0),
                ];

                $prepared[] = $row;
                $recencyDistribution[] = $row['recency_days'];
                $frequencyDistribution[] = $row['frequency_orders'];
                $monetaryDistribution[] = $row['monetary_value'];
            }

            $scores = [];

            foreach ($prepared as $row) {
                $expiry = $this->repository->latestSubscriptionExpiry($row['customer']->id);
                $result = $this->scorer->score(
                    recencyDays: $row['recency_days'],
                    frequencyOrders: $row['frequency_orders'],
                    monetaryValue: $row['monetary_value'],
                    recencyDistribution: $recencyDistribution,
                    frequencyDistribution: $frequencyDistribution,
                    monetaryDistribution: $monetaryDistribution,
                    subscriptionExpiresAt: $expiry,
                );

                $scores[] = $this->repository->saveRfmScore($row['customer'], $store, $run, [
                    'recency_days' => $result->recencyDays,
                    'frequency_orders' => $result->frequencyOrders,
                    'monetary_value' => number_format($result->monetaryValue, 4, '.', ''),
                    'r_score' => $result->rScore,
                    'f_score' => $result->fScore,
                    'm_score' => $result->mScore,
                    'segment' => $result->segment,
                    'subscription_expires_at' => $expiry,
                    'churn_probability' => $result->churnProbability,
                    'signals' => $result->signals,
                ]);
            }

            $this->repository->finishMetricRun($run, [
                'customers_scored' => count($scores),
                'high_churn_customers' => collect($scores)->where('churn_probability', '>=', 0.65)->count(),
            ]);

            return $scores;
        } catch (Throwable $exception) {
            $this->repository->failMetricRun($run, $exception->getMessage());

            throw $exception;
        }
    }
}
