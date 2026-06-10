<?php

namespace App\Services\Analytics\Scoring;

use App\Services\Analytics\DTO\FraudRiskResult;

class FraudRiskScorer
{
    /**
     * This is a deterministic v1 approximation of an Isolation Forest.
     * Each suspicious behavior adds an anomaly contribution; later FastAPI can replace this without changing tables.
     */
    public function score(array $features): FraudRiskResult
    {
        $score = 0.0;
        $evidence = [];

        $orderSpeedSeconds = (int) ($features['order_speed_seconds'] ?? 999999);
        if ($orderSpeedSeconds <= 60) {
            $score += 22;
            $evidence['rapid_order_speed_seconds'] = $orderSpeedSeconds;
        } elseif ($orderSpeedSeconds <= 300) {
            $score += 12;
            $evidence['fast_order_speed_seconds'] = $orderSpeedSeconds;
        }

        $ipOrderCount = (int) ($features['ip_order_count_24h'] ?? 0);
        if ($ipOrderCount >= 8) {
            $score += 26;
            $evidence['ip_order_count_24h'] = $ipOrderCount;
        } elseif ($ipOrderCount >= 4) {
            $score += 14;
            $evidence['ip_order_count_24h'] = $ipOrderCount;
        }

        $sharedIpCustomers = (int) ($features['shared_ip_customer_count'] ?? 0);
        if ($sharedIpCustomers >= 5) {
            $score += 22;
            $evidence['shared_ip_customer_count'] = $sharedIpCustomers;
        } elseif ($sharedIpCustomers >= 3) {
            $score += 12;
            $evidence['shared_ip_customer_count'] = $sharedIpCustomers;
        }

        $freeTrials = (int) ($features['free_trial_count_30d'] ?? 0);
        if ($freeTrials >= 4) {
            $score += 24;
            $evidence['free_trial_count_30d'] = $freeTrials;
        } elseif ($freeTrials >= 2) {
            $score += 12;
            $evidence['free_trial_count_30d'] = $freeTrials;
        }

        $email = strtolower((string) ($features['email'] ?? ''));
        if ($email !== '' && $this->looksDisposableOrTestEmail($email)) {
            $score += 16;
            $evidence['suspicious_email_pattern'] = $email;
        }

        $isTrial = (bool) ($features['is_trial_order'] ?? false);
        $orderTotal = (float) ($features['order_total'] ?? 0);
        if ($isTrial && $orderTotal <= 0.01) {
            $score += 10;
            $evidence['zero_value_trial_order'] = true;
        }

        $score = round(min(100, $score), 2);

        return new FraudRiskResult(
            riskScore: $score,
            severity: $this->severity($score),
            signalType: $this->signalType($evidence),
            evidence: $evidence,
        );
    }

    private function looksDisposableOrTestEmail(string $email): bool
    {
        $local = explode('@', $email)[0] ?? '';
        $domain = explode('@', $email)[1] ?? '';

        $disposableDomains = [
            'mailinator.com',
            'tempmail.com',
            '10minutemail.com',
            'guerrillamail.com',
            'yopmail.com',
        ];

        return in_array($domain, $disposableDomains, true)
            || preg_match('/(^test|test$|trial|fake|demo|\+\d{3,})/', $local) === 1;
    }

    private function severity(float $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 35 => 'medium',
            default => 'low',
        };
    }

    private function signalType(array $evidence): string
    {
        if (isset($evidence['free_trial_count_30d'], $evidence['shared_ip_customer_count'])) {
            return 'trial_abuse_cluster';
        }

        if (isset($evidence['ip_order_count_24h'])) {
            return 'order_velocity_anomaly';
        }

        if (isset($evidence['suspicious_email_pattern'])) {
            return 'email_pattern_anomaly';
        }

        return 'low_risk';
    }
}
