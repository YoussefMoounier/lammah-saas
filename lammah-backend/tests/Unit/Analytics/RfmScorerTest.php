<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Scoring\RfmScorer;
use Tests\TestCase;

class RfmScorerTest extends TestCase
{
    public function test_it_segments_recent_high_value_repeat_buyers_as_vip(): void
    {
        $result = (new RfmScorer())->score(
            recencyDays: 2,
            frequencyOrders: 10,
            monetaryValue: 900,
            recencyDistribution: [2, 10, 30, 60, 120],
            frequencyDistribution: [1, 2, 5, 8, 10],
            monetaryDistribution: [50, 100, 250, 500, 900],
            subscriptionExpiresAt: now()->addMonths(3),
        );

        $this->assertSame(5, $result->rScore);
        $this->assertSame(5, $result->fScore);
        $this->assertSame(5, $result->mScore);
        $this->assertSame('vip', $result->segment);
        $this->assertLessThan(0.25, $result->churnProbability);
    }

    public function test_it_segments_expiring_customers_as_churn_risk(): void
    {
        $result = (new RfmScorer())->score(
            recencyDays: 90,
            frequencyOrders: 2,
            monetaryValue: 120,
            recencyDistribution: [2, 10, 30, 60, 90],
            frequencyDistribution: [1, 2, 5, 8, 10],
            monetaryDistribution: [50, 100, 120, 500, 900],
            subscriptionExpiresAt: now()->addDays(3),
        );

        $this->assertSame('churn_risk', $result->segment);
        $this->assertGreaterThanOrEqual(0.45, $result->churnProbability);
    }
}
