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

        return $response->json();
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
