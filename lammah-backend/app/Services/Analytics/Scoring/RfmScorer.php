<?php

namespace App\Services\Analytics\Scoring;

use App\Services\Analytics\DTO\RfmScoreResult;
use Illuminate\Support\Carbon;

class RfmScorer
{
    /**
     * @param array<int, int|float> $recencyDistribution
     * @param array<int, int|float> $frequencyDistribution
     * @param array<int, int|float> $monetaryDistribution
     */
    public function score(
        int $recencyDays,
        int $frequencyOrders,
        float $monetaryValue,
        array $recencyDistribution,
        array $frequencyDistribution,
        array $monetaryDistribution,
        ?Carbon $subscriptionExpiresAt = null,
    ): RfmScoreResult {
        $rScore = $this->quantileScore($recencyDays, $recencyDistribution, higherIsBetter: false);
        $fScore = $this->quantileScore($frequencyOrders, $frequencyDistribution, higherIsBetter: true);
        $mScore = $this->quantileScore($monetaryValue, $monetaryDistribution, higherIsBetter: true);
        $signals = [];

        if ($subscriptionExpiresAt !== null) {
            $daysUntilExpiry = now()->startOfDay()->diffInDays($subscriptionExpiresAt->copy()->startOfDay(), false);
            $signals['days_until_subscription_expiry'] = $daysUntilExpiry;
        } else {
            $daysUntilExpiry = null;
        }

        $churnProbability = $this->churnProbability($recencyDays, $fScore, $mScore, $daysUntilExpiry);
        $segment = $this->segment($rScore, $fScore, $mScore, $churnProbability, $daysUntilExpiry);

        return new RfmScoreResult(
            recencyDays: $recencyDays,
            frequencyOrders: $frequencyOrders,
            monetaryValue: round($monetaryValue, 4),
            rScore: $rScore,
            fScore: $fScore,
            mScore: $mScore,
            segment: $segment,
            churnProbability: round($churnProbability, 2),
            signals: $signals,
        );
    }

    private function quantileScore(int|float $value, array $distribution, bool $higherIsBetter): int
    {
        $values = array_values(array_filter(array_map('floatval', $distribution), fn (float $item): bool => $item >= 0));

        if ($values === []) {
            return 3;
        }

        sort($values);
        $lessOrEqual = 0;

        foreach ($values as $candidate) {
            if ($candidate <= (float) $value) {
                $lessOrEqual++;
            }
        }

        $percentile = $lessOrEqual / count($values);
        $score = (int) max(1, min(5, ceil($percentile * 5)));

        return $higherIsBetter ? $score : 6 - $score;
    }

    private function churnProbability(int $recencyDays, int $fScore, int $mScore, ?int $daysUntilExpiry): float
    {
        $probability = 0.10;
        $probability += min(0.45, ($recencyDays / 180) * 0.45);
        $probability -= (($fScore - 1) * 0.04);
        $probability -= (($mScore - 1) * 0.03);

        if ($daysUntilExpiry !== null) {
            if ($daysUntilExpiry < 0) {
                $probability += 0.35;
            } elseif ($daysUntilExpiry <= 7) {
                $probability += 0.25;
            } elseif ($daysUntilExpiry <= 30) {
                $probability += 0.15;
            }
        }

        return max(0.01, min(0.95, $probability));
    }

    private function segment(int $rScore, int $fScore, int $mScore, float $churnProbability, ?int $daysUntilExpiry): string
    {
        if ($fScore >= 4 && $mScore >= 4 && $churnProbability >= 0.55) {
            return 'vip_at_risk';
        }

        if ($rScore >= 4 && $fScore >= 4 && $mScore >= 4) {
            return 'vip';
        }

        if ($churnProbability >= 0.65 || ($daysUntilExpiry !== null && $daysUntilExpiry <= 7)) {
            return 'churn_risk';
        }

        if ($rScore >= 4 && $fScore <= 2) {
            return 'new_or_reactivated';
        }

        if ($rScore <= 2 && $fScore <= 2) {
            return 'dormant';
        }

        return 'steady';
    }
}
