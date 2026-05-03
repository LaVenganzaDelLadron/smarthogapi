<?php

namespace App\Services;

use App\Models\HogHealthPredictions;
use App\Models\Hogs;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    private const TIMEOUT_SECONDS = 3;

    private const CACHE_TTL_HOURS = 24;

    private const HEALTH_CHECK_CACHE_MINUTES = 5;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.prediction_api.url');
    }

    /**
     * Predict hog health status with fallback caching
     */
    public function predictHogHealth(int $hogId): array
    {
        try {
            $hog = Hogs::findOrFail($hogId)->load('hogpen', 'hogDailyRecords');

            // Check ML service health
            if (! $this->isServiceHealthy()) {
                Log::warning("ML service unhealthy. Using cached prediction for hog {$hogId}");

                return $this->getCachedPrediction($hogId);
            }

            // Attempt prediction from FastAPI
            $prediction = $this->callPredictionAPI($hog);

            // Cache successful result
            $this->cachePrediction($hogId, $prediction);

            // Store in database
            HogHealthPredictions::create([
                'hog_id' => $hogId,
                'ml_model_id' => $prediction['model_id'] ?? null,
                'predicted_status' => $prediction['predicted_status'],
                'risk_score' => $prediction['risk_score'],
            ]);

            // Update hog's real-time health status
            $hog->update(['health_status' => $this->mapPredictionToStatus($prediction['predicted_status'])]);

            Log::info("Prediction successful for hog {$hogId}", [
                'status' => $prediction['predicted_status'],
                'risk_score' => $prediction['risk_score'],
            ]);

            return [
                'success' => true,
                'data' => $prediction,
                'cached' => false,
                'ml_service_status' => 'healthy',
            ];
        } catch (Exception $e) {
            Log::error("Prediction failed for hog {$hogId}: {$e->getMessage()}");

            // Try to return cached prediction
            return $this->getCachedPrediction($hogId);
        }
    }

    /**
     * Check if ML service is healthy
     */
    private function isServiceHealthy(): bool
    {
        $cacheKey = 'ml_service_health_status';

        // Return cached health status if available (Redis DB 1)
        $cached = Cache::store('redis')->get($cacheKey);
        if ($cached !== null) {
            return $cached === 'healthy';
        }

        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/");

            $isHealthy = $response->successful();

            // Cache health status for 5 minutes in Redis
            Cache::store('redis')->put($cacheKey, $isHealthy ? 'healthy' : 'unhealthy', now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            if (! $isHealthy) {
                Log::warning("ML service health check failed with status {$response->status()}");
            }

            return $isHealthy;
        } catch (Exception $e) {
            Log::warning("ML service health check error: {$e->getMessage()}");

            // Cache as unhealthy
            Cache::store('redis')->put($cacheKey, 'unhealthy', now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            return false;
        }
    }

    /**
     * Call FastAPI prediction endpoint
     */
    private function callPredictionAPI(Hogs $hog): array
    {
        $payload = [
            'hog_id' => $hog->id,
            'weight' => $hog->weight_current,
            'age' => $hog->current_age,
            'health_status' => $hog->health_status,
            'pen_id' => $hog->hog_pen_id,
        ];

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->post("{$this->baseUrl}/predict/hog-health", $payload);

        if (! $response->successful()) {
            throw new Exception("ML API error: HTTP {$response->status()}");
        }

        // New response format has 'prediction' nested inside
        $data = $response->json();
        if (isset($data['prediction'])) {
            return $data['prediction'];
        }

        return $data;
    }

    /**
     * Cache prediction result in Redis
     */
    private function cachePrediction(int $hogId, array $prediction): void
    {
        $cacheKey = "hog_prediction_{$hogId}";
        // Store in Redis cache database (DB 1) with 24-hour TTL
        Cache::store('redis')->put($cacheKey, $prediction, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Get cached prediction from Redis or return failure
     */
    private function getCachedPrediction(int $hogId): array
    {
        $cacheKey = "hog_prediction_{$hogId}";
        $cached = Cache::store('redis')->get($cacheKey);

        if ($cached) {
            Log::info("Returning cached prediction for hog {$hogId}");

            return [
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'ml_service_status' => 'unavailable',
                'warning' => 'ML service unavailable. Using cached prediction.',
            ];
        }

        Log::error("No cached prediction available for hog {$hogId}");

        return [
            'success' => false,
            'cached' => false,
            'ml_service_status' => 'unavailable',
            'error' => 'ML service unavailable and no cached prediction exists.',
        ];
    }

    /**
     * Batch predict all hogs (scheduled task)
     */
    public function predictAllHogs(): array
    {
        $hogsCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        try {
            $hogs = Hogs::all();

            foreach ($hogs as $hog) {
                $hogsCount++;

                try {
                    $result = $this->predictHogHealth($hog->id);

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $errors[] = "Hog {$hog->id}: {$result['error']}";
                    }
                } catch (Exception $e) {
                    $failureCount++;
                    $errors[] = "Hog {$hog->id}: {$e->getMessage()}";
                }
            }

            $summary = [
                'success' => true,
                'total_hogs' => $hogsCount,
                'successful_predictions' => $successCount,
                'failed_predictions' => $failureCount,
                'errors' => $errors,
            ];

            Log::info('Batch predictions completed', $summary);

            return $summary;
        } catch (Exception $e) {
            Log::error("Batch prediction failed: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Predict feed demand for a farm
     */
    public function predictFeedDemand(int $farmId): array
    {
        try {
            if (! $this->isServiceHealthy()) {
                Log::warning("ML service unhealthy. Using cached feed demand for farm {$farmId}");

                return $this->getCachedFeedDemand($farmId);
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/feed-demand", ['farm_id' => $farmId]);

            if (! $response->successful()) {
                throw new Exception("ML API error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $prediction = isset($data['prediction']) ? $data['prediction'] : $data;
            $this->cacheFeedDemand($farmId, $prediction);

            Log::info("Feed demand forecast for farm {$farmId}", [
                'tomorrow_kg' => $prediction['tomorrow_feed_kg'] ?? 0,
                'weekly_kg' => $prediction['weekly_feed_kg'] ?? 0,
            ]);

            return [
                'success' => true,
                'data' => $prediction,
                'cached' => false,
            ];
        } catch (Exception $e) {
            Log::error("Feed demand prediction failed for farm {$farmId}: {$e->getMessage()}");

            return $this->getCachedFeedDemand($farmId);
        }
    }

    /**
     * Predict weight growth for a hog
     */
    public function predictWeightGrowth(int $hogId): array
    {
        try {
            if (! $this->isServiceHealthy()) {
                return $this->getCachedWeightGrowth($hogId);
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/weight-growth", ['hog_id' => $hogId]);

            if (! $response->successful()) {
                throw new Exception("ML API error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $prediction = isset($data['prediction']) ? $data['prediction'] : $data;
            $this->cacheWeightGrowth($hogId, $prediction);

            Log::info("Weight growth forecast for hog {$hogId}", [
                'next_week_kg' => $prediction['next_week_weight'] ?? 0,
                'next_month_kg' => $prediction['next_month_weight'] ?? 0,
            ]);

            return [
                'success' => true,
                'data' => $prediction,
                'cached' => false,
            ];
        } catch (Exception $e) {
            Log::error("Weight growth prediction failed for hog {$hogId}: {$e->getMessage()}");

            return $this->getCachedWeightGrowth($hogId);
        }
    }

    /**
     * Predict disease outbreak risk for a pen
     */
    public function predictOutbreakRisk(int $penId): array
    {
        try {
            if (! $this->isServiceHealthy()) {
                return $this->getCachedOutbreakRisk($penId);
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/outbreak-risk", ['pen_id' => $penId]);

            if (! $response->successful()) {
                throw new Exception("ML API error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $prediction = isset($data['prediction']) ? $data['prediction'] : $data;
            $this->cacheOutbreakRisk($penId, $prediction);

            if (($prediction['risk_level'] ?? 'LOW') === 'HIGH') {
                Log::critical("High outbreak risk detected for pen {$penId}", $prediction);
            } else {
                Log::info("Outbreak risk assessment for pen {$penId}", [
                    'risk_level' => $prediction['risk_level'] ?? 'LOW',
                    'risk_score' => $prediction['risk_score'] ?? 0,
                ]);
            }

            return [
                'success' => true,
                'data' => $prediction,
                'cached' => false,
            ];
        } catch (Exception $e) {
            Log::error("Outbreak risk prediction failed for pen {$penId}: {$e->getMessage()}");

            return $this->getCachedOutbreakRisk($penId);
        }
    }

    /**
     * Predict device maintenance risk
     */
    public function predictDeviceRisk(int $deviceId): array
    {
        try {
            if (! $this->isServiceHealthy()) {
                return $this->getCachedDeviceRisk($deviceId);
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/device-risk", ['device_id' => $deviceId]);

            if (! $response->successful()) {
                throw new Exception("ML API error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $prediction = isset($data['prediction']) ? $data['prediction'] : $data;
            $this->cacheDeviceRisk($deviceId, $prediction);

            if (($prediction['status'] ?? 'Normal') !== 'Normal') {
                Log::warning("Device {$deviceId} maintenance alert", [
                    'status' => $prediction['status'] ?? 'Unknown',
                    'days_until_failure' => $prediction['days_until_failure'] ?? 'Unknown',
                ]);
            }

            return [
                'success' => true,
                'data' => $prediction,
                'cached' => false,
            ];
        } catch (Exception $e) {
            Log::error("Device risk prediction failed for device {$deviceId}: {$e->getMessage()}");

            return $this->getCachedDeviceRisk($deviceId);
        }
    }

    /**
     * Get cached feed demand
     */
    private function getCachedFeedDemand(int $farmId): array
    {
        $cached = Cache::store('redis')->get("feed_demand_{$farmId}");

        return $cached
            ? [
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'warning' => 'Using cached forecast',
            ]
            : [
                'success' => false,
                'cached' => false,
                'error' => 'Feed demand forecast unavailable',
            ];
    }

    /**
     * Cache feed demand
     */
    private function cacheFeedDemand(int $farmId, array $data): void
    {
        Cache::store('redis')->put("feed_demand_{$farmId}", $data, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Get cached weight growth
     */
    private function getCachedWeightGrowth(int $hogId): array
    {
        $cached = Cache::store('redis')->get("weight_growth_{$hogId}");

        return $cached
            ? [
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'warning' => 'Using cached forecast',
            ]
            : [
                'success' => false,
                'cached' => false,
                'error' => 'Weight growth forecast unavailable',
            ];
    }

    /**
     * Cache weight growth
     */
    private function cacheWeightGrowth(int $hogId, array $data): void
    {
        Cache::store('redis')->put("weight_growth_{$hogId}", $data, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Get cached outbreak risk
     */
    private function getCachedOutbreakRisk(int $penId): array
    {
        $cached = Cache::store('redis')->get("outbreak_risk_{$penId}");

        return $cached
            ? [
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'warning' => 'Using cached assessment',
            ]
            : [
                'success' => false,
                'cached' => false,
                'error' => 'Outbreak risk assessment unavailable',
            ];
    }

    /**
     * Cache outbreak risk
     */
    private function cacheOutbreakRisk(int $penId, array $data): void
    {
        Cache::store('redis')->put("outbreak_risk_{$penId}", $data, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Get cached device risk
     */
    private function getCachedDeviceRisk(int $deviceId): array
    {
        $cached = Cache::store('redis')->get("device_risk_{$deviceId}");

        return $cached
            ? [
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'warning' => 'Using cached assessment',
            ]
            : [
                'success' => false,
                'cached' => false,
                'error' => 'Device risk assessment unavailable',
            ];
    }

    /**
     * Cache device risk
     */
    private function cacheDeviceRisk(int $deviceId, array $data): void
    {
        Cache::store('redis')->put("device_risk_{$deviceId}", $data, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Map ML prediction status to hog health_status
     */
    private function mapPredictionToStatus(string $predictedStatus): string
    {
        return match ($predictedStatus) {
            'healthy' => 'good',
            'at_risk' => 'caution',
            'sick' => 'sick',
            default => 'unknown',
        };
    }
}

