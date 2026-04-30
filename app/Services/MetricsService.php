<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class MetricsService
{
    /**
     * Increment feeding attempts counter for a feeder
     */
    public function incrementFeedingAttempts(int $feederId): int
    {
        return Redis::incr("feeder:{$feederId}:attempts");
    }

    /**
     * Increment prediction API calls counter
     */
    public function incrementPredictionCalls(): int
    {
        return Redis::incr('prediction:api-calls');
    }

    /**
     * Increment error counter for a specific error type
     */
    public function incrementErrors(string $type): int
    {
        return Redis::incr("errors:{$type}");
    }

    /**
     * Get all metrics for a specific feeder
     */
    public function getFeedingMetrics(int $feederId): array
    {
        return [
            'attempts' => (int) (Redis::get("feeder:{$feederId}:attempts") ?? 0),
            'last_feed' => Redis::get("feeder:{$feederId}:last-feed"),
            'total_dispensed' => (float) (Redis::get("feeder:{$feederId}:total-dispensed") ?? 0),
        ];
    }

    /**
     * Get all system metrics across all feeders
     */
    public function getAllMetrics(): array
    {
        $metrics = [];
        $keys = Redis::keys('feeder:*:attempts');

        foreach ($keys as $key) {
            $metrics[$key] = (int) Redis::get($key);
        }

        return $metrics;
    }

    /**
     * Record last feeding time for a feeder
     */
    public function recordLastFeedTime(int $feederId, string $timestamp): void
    {
        Redis::set("feeder:{$feederId}:last-feed", $timestamp);
    }

    /**
     * Record total amount dispensed by a feeder
     */
    public function recordTotalDispensed(int $feederId, float $amount): void
    {
        Redis::incr("feeder:{$feederId}:total-dispensed", intval($amount * 100));
    }

    /**
     * Reset all metrics (admin only)
     */
    public function resetAllMetrics(): int
    {
        $keys = array_merge(
            Redis::keys('feeder:*:attempts'),
            Redis::keys('errors:*'),
            Redis::keys('prediction:*')
        );

        if (count($keys) > 0) {
            Redis::del(...$keys);
        }

        return count($keys);
    }

    /**
     * Get error count for a specific type
     */
    public function getErrorCount(string $type): int
    {
        return (int) (Redis::get("errors:{$type}") ?? 0);
    }

    /**
     * Get all error counts
     */
    public function getAllErrorCounts(): array
    {
        $errors = [];
        $keys = Redis::keys('errors:*');

        foreach ($keys as $key) {
            $errors[$key] = (int) Redis::get($key);
        }

        return $errors;
    }
}
