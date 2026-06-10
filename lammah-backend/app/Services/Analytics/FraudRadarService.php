<?php

namespace App\Services\Analytics;

use App\Models\FraudRiskSignal;
use App\Models\WooCommerceStore;
use App\Models\WooOrder;
use App\Repositories\Analytics\AnalyticsRepository;
use App\Services\Analytics\Scoring\FraudRiskScorer;
use Illuminate\Support\Carbon;
use Throwable;

class FraudRadarService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly FraudRiskScorer $scorer,
    ) {
    }

    /**
     * @return array<int, FraudRiskSignal>
     */
    public function scanStore(WooCommerceStore $store, int $lookbackDays = 30, float $minimumScore = 35.0): array
    {
        $since = now()->subDays($lookbackDays);
        $run = $this->repository->startMetricRun(
            merchantId: $store->merchant_id,
            storeId: $store->id,
            metricType: 'fraud_radar',
            windowStart: $since,
            windowEnd: now(),
            parameters: ['lookback_days' => $lookbackDays, 'minimum_score' => $minimumScore],
        );

        try {
            $orders = $this->repository->recentFraudCandidates($store, $since);
            $lastSeenByIdentity = [];
            $signals = [];

            foreach ($orders as $order) {
                $result = $this->scorer->score($this->featuresForOrder($store, $order, $since, $lastSeenByIdentity));

                if ($result->riskScore >= $minimumScore) {
                    $signals[] = $this->repository->saveFraudSignal($order, $run, [
                        'risk_score' => $result->riskScore,
                        'severity' => $result->severity,
                        'signal_type' => $result->signalType,
                        'evidence' => $result->evidence,
                    ]);
                }
            }

            $this->repository->finishMetricRun($run, [
                'orders_scanned' => $orders->count(),
                'signals_created' => count($signals),
            ]);

            return $signals;
        } catch (Throwable $exception) {
            $this->repository->failMetricRun($run, $exception->getMessage());

            throw $exception;
        }
    }

    private function featuresForOrder(
        WooCommerceStore $store,
        WooOrder $order,
        Carbon $since,
        array &$lastSeenByIdentity,
    ): array {
        $identity = $order->customer_email_hash ?: $order->customer_ip_hash ?: $order->customer_id ?: $order->id;
        $orderTime = $order->woo_created_at ?: $order->created_at ?: now();
        $previousTime = $lastSeenByIdentity[$identity] ?? null;
        $lastSeenByIdentity[$identity] = $orderTime;

        return [
            'order_speed_seconds' => $previousTime === null ? 999999 : $previousTime->diffInSeconds($orderTime),
            'ip_order_count_24h' => $this->repository->orderCountForHash($store->id, 'customer_ip_hash', $order->customer_ip_hash, $orderTime->copy()->subDay()),
            'email_order_count_24h' => $this->repository->orderCountForHash($store->id, 'customer_email_hash', $order->customer_email_hash, $orderTime->copy()->subDay()),
            'shared_ip_customer_count' => $this->repository->customerCountForIpHash($store->id, $order->customer_ip_hash),
            'free_trial_count_30d' => $this->repository->trialCountForEmailHash($store->id, $order->customer_email_hash, $since),
            'email' => $order->customer_email_encrypted,
            'order_total' => (float) $order->total,
            'is_trial_order' => $this->looksLikeTrialOrder($order),
        ];
    }

    private function looksLikeTrialOrder(WooOrder $order): bool
    {
        if ((float) $order->total <= 0.01) {
            return true;
        }

        foreach (($order->raw_payload['line_items'] ?? []) as $item) {
            if (stripos((string) ($item['name'] ?? ''), 'trial') !== false) {
                return true;
            }
        }

        return false;
    }
}
