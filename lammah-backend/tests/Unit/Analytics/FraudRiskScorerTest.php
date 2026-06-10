<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Scoring\FraudRiskScorer;
use Tests\TestCase;

class FraudRiskScorerTest extends TestCase
{
    public function test_it_flags_trial_abuse_clusters_as_critical(): void
    {
        $result = (new FraudRiskScorer())->score([
            'order_speed_seconds' => 45,
            'ip_order_count_24h' => 9,
            'shared_ip_customer_count' => 6,
            'free_trial_count_30d' => 5,
            'email' => 'test+999@mailinator.com',
            'order_total' => 0,
            'is_trial_order' => true,
        ]);

        $this->assertGreaterThanOrEqual(80, $result->riskScore);
        $this->assertSame('critical', $result->severity);
        $this->assertSame('trial_abuse_cluster', $result->signalType);
    }

    public function test_it_keeps_normal_paid_orders_low_risk(): void
    {
        $result = (new FraudRiskScorer())->score([
            'order_speed_seconds' => 999999,
            'ip_order_count_24h' => 1,
            'shared_ip_customer_count' => 1,
            'free_trial_count_30d' => 0,
            'email' => 'customer@example.com',
            'order_total' => 120,
            'is_trial_order' => false,
        ]);

        $this->assertLessThan(35, $result->riskScore);
        $this->assertSame('low', $result->severity);
    }
}
