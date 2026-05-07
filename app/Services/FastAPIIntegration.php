<?php

namespace App\Services;

use App\Models\FeedingPredictions;
use App\Models\HogHealthPredictions;
use App\Models\Hogpens;
use App\Models\MLModels;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrates with the FastAPI ML service for predictions
 *
 * Maps FastAPI endpoints to Laravel models and handles:
 * - Feed recommendations (/predict/feed-recommendation)
 * - Weight trend predictions (/predict/weight-trend)
 * - Pen status classification (/predict/pen-status-classification)
 */
class FastAPIIntegration
{
    private const TIMEOUT_SECONDS = 30;

    private const HEALTH_CHECK_CACHE_MINUTES = 5;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.fastapi.url') ?? 'http://localhost:5000';
    }

    /**
     * Predict feed recommendation for a hog pen
     *
     * @param  array  $overrides  Optional field overrides (pig_age_days, avg_weight_kg, etc.)
     * @return array Feed recommendation with full response structure
     */
    public function predictFeedRecommendation(int $penId, array $overrides = []): array
    {
        try {
            $pen = Hogpens::with('hogs', 'farm', 'feeder')->findOrFail($penId);

            // Build the request payload from pen data
            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI feed recommendation for pen {$penId}");

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/feed-recommendation", $payload);

            if (! $response->successful()) {
                throw new Exception("FastAPI error: HTTP {$response->status()} - {$response->body()}");
            }

            $data = $response->json();

            // Store in database
            $prediction = FeedingPredictions::create([
                'hog_pen_id' => $penId,
                'ml_model_id' => $this->getOrCreateModel($data['model_used'] ?? 'feed_recommendation'),
                'predicted_feed_amount' => $data['feed_recommendation']['recommended_feed_per_pig_per_day'] ?? 0,
                'confidence_score' => $data['feed_recommendation']['confidence_score'] ?? $data['input']['confidence_score'] ?? 0,
                'model_used' => $data['model_used'] ?? null,
                'confidence_level' => $data['confidence_level'] ?? 'unknown',
                'confidence_reason' => $data['confidence_reason'] ?? null,
                'feed_recommendation' => $data['feed_recommendation'] ?? null,
                'feed_totals' => $data['feed_totals'] ?? null,
                'weight_trend' => $data['weight_trend'] ?? null,
                'pen_status' => $data['pen_status'] ?? null,
                'warnings' => $data['warnings'] ?? [],
                'alerts' => $data['alerts'] ?? [],
                'suggestions' => $data['suggestions'] ?? [],
                'fastapi_response' => $data,
                'predicted_at' => now(),
            ]);

            Log::info("Feed prediction stored for pen {$penId}", [
                'prediction_id' => $prediction->id,
                'confidence_score' => $prediction->confidence_score,
            ]);

            return [
                'success' => true,
                'prediction_id' => $prediction->id,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Feed recommendation prediction failed for pen {$penId}: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Predict weight trend for a hog pen
     *
     * @param  array  $overrides  Optional field overrides
     * @return array Weight trend prediction with confidence metrics
     */
    public function predictWeightTrend(int $penId, array $overrides = []): array
    {
        try {
            $pen = Hogpens::with('hogs', 'farm')->findOrFail($penId);

            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI weight trend prediction for pen {$penId}");

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/weight-trend", $payload);

            if (! $response->successful()) {
                throw new Exception("FastAPI error: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Store in health predictions table
            $prediction = HogHealthPredictions::create([
                'hog_id' => $pen->hogs->first()->id ?? null,
                'ml_model_id' => $this->getOrCreateModel($data['model_used'] ?? 'weight_trend'),
                'predicted_status' => 'weight_trending',
                'risk_score' => 100 - ($data['confidence'] * 100),
                'model_used' => $data['model_used'] ?? null,
                'confidence_level' => $data['confidence_level'] ?? 'unknown',
                'confidence_reason' => $data['confidence_reason'] ?? null,
                'weight_trend' => $data['weight_trend'] ?? null,
                'warnings' => $data['warnings'] ?? [],
                'metrics' => $data['metrics'] ?? null,
                'fastapi_response' => $data,
                'predicted_at' => now(),
            ]);

            Log::info("Weight trend prediction stored for pen {$penId}", [
                'confidence' => $data['confidence'] ?? 'unknown',
            ]);

            return [
                'success' => true,
                'prediction_id' => $prediction->id,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Weight trend prediction failed: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Predict pen status classification
     *
     * @param  array  $overrides  Optional field overrides
     * @return array Pen status with classification confidence
     */
    public function predictPenStatus(int $penId, array $overrides = []): array
    {
        try {
            $pen = Hogpens::with('hogs', 'farm')->findOrFail($penId);

            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI pen status classification for pen {$penId}");

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/pen-status-classification", $payload);

            if (! $response->successful()) {
                throw new Exception("FastAPI error: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Store pen status in health predictions
            $penStatus = $data['pen_status'] ?? [];

            $prediction = HogHealthPredictions::create([
                'hog_id' => $pen->hogs->first()->id ?? null,
                'ml_model_id' => $this->getOrCreateModel($data['model_used'] ?? 'pen_status'),
                'predicted_status' => $penStatus['status'] ?? 'unknown',
                'risk_score' => 100 - ($penStatus['confidence_score'] * 100),
                'model_used' => $data['model_used'] ?? null,
                'confidence_level' => $data['confidence_level'] ?? 'unknown',
                'confidence_reason' => $data['confidence_reason'] ?? null,
                'pen_status' => $penStatus,
                'warnings' => $data['warnings'] ?? [],
                'metrics' => $data['metrics'] ?? null,
                'fastapi_response' => $data,
                'predicted_at' => now(),
            ]);

            Log::info('Pen status prediction stored', [
                'status' => $penStatus['status'] ?? 'unknown',
                'confidence' => $penStatus['confidence_score'] ?? 0,
            ]);

            return [
                'success' => true,
                'prediction_id' => $prediction->id,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Pen status prediction failed: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch predict feed recommendations for multiple pens
     *
     * @param  array  $penIds  Array of pen IDs
     * @return array Results for all pens
     */
    public function batchPredictFeedRecommendation(array $penIds): array
    {
        $items = [];

        foreach ($penIds as $penId) {
            $pen = Hogpens::with('hogs', 'farm', 'feeder')->find($penId);
            if ($pen) {
                $items[] = $this->buildFeedRequestPayload($pen);
            }
        }

        if (empty($items)) {
            return ['success' => false, 'error' => 'No valid pens found'];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->post("{$this->baseUrl}/predict/batch/feed-recommendation", ['items' => $items]);

            if (! $response->successful()) {
                throw new Exception("FastAPI batch error: HTTP {$response->status()}");
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            foreach ($results as $index => $result) {
                if (isset($penIds[$index])) {
                    try {
                        FeedingPredictions::create([
                            'hog_pen_id' => $penIds[$index],
                            'ml_model_id' => $this->getOrCreateModel($result['model_used'] ?? 'feed_recommendation'),
                            'predicted_feed_amount' => $result['feed_recommendation']['recommended_feed_per_pig_per_day'] ?? 0,
                            'confidence_score' => $result['feed_recommendation']['confidence_score'] ?? 0,
                            'model_used' => $result['model_used'] ?? null,
                            'confidence_level' => $result['confidence_level'] ?? 'unknown',
                            'confidence_reason' => $result['confidence_reason'] ?? null,
                            'feed_recommendation' => $result['feed_recommendation'] ?? null,
                            'feed_totals' => $result['feed_totals'] ?? null,
                            'weight_trend' => $result['weight_trend'] ?? null,
                            'pen_status' => $result['pen_status'] ?? null,
                            'warnings' => $result['warnings'] ?? [],
                            'alerts' => $result['alerts'] ?? [],
                            'suggestions' => $result['suggestions'] ?? [],
                            'fastapi_response' => $result,
                            'predicted_at' => now(),
                        ]);
                    } catch (Exception $e) {
                        Log::error("Failed to store batch prediction for pen {$penIds[$index]}: {$e->getMessage()}");
                    }
                }
            }

            return [
                'success' => true,
                'count' => count($results),
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Batch feed prediction failed: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check FastAPI service health
     */
    public function healthCheck(): bool
    {
        $cacheKey = 'fastapi_health_status';

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 'healthy';
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            $isHealthy = $response->successful() && $response->json()['status'] === 'ok';

            Cache::put($cacheKey, $isHealthy ? 'healthy' : 'unhealthy',
                now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            return $isHealthy;
        } catch (Exception $e) {
            Log::warning("FastAPI health check failed: {$e->getMessage()}");
            Cache::put($cacheKey, 'unhealthy', now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            return false;
        }
    }

    /**
     * Build FeedRequest payload from pen data
     */
    private function buildFeedRequestPayload(Hogpens $pen, array $overrides = []): array
    {
        $hog = $pen->hogs->first();

        return array_merge([
            'pig_age_days' => $hog ? $hog->age_days : 0,
            'avg_weight_kg' => $hog ? $hog->weight_current : 0,
            'growth_stage' => $hog ? $hog->current_stage : 'unknown',
            'current_feed_kg' => $hog ? ($hog->dailyFeedConsumption ?? 0) : 0,
            'pen_capacity' => $pen->capacity ?? 1,
            'device_code' => $pen->feeder?->device_code ?? 'unknown',
            'feeding_times' => $this->extractFeedingTimes($pen),
            'num_pens' => $pen->farm?->hogpens()->count() ?? 1,
            'feed_type' => $pen->current_feed_type ?? null,
        ], $overrides);
    }

    /**
     * Extract feeding times from feeding schedule
     * FastAPI expects exactly 3 feeding times
     */
    private function extractFeedingTimes(Hogpens $pen): array
    {
        $times = [];

        if ($pen->feedingSchedule) {
            foreach ($pen->feedingSchedule as $schedule) {
                $times[] = $schedule->feeding_time ?? '08:00';
            }
        }

        // Pad with defaults if needed
        while (count($times) < 3) {
            $times[] = '08:00';
        }

        return array_slice($times, 0, 3);
    }

    /**
     * Get or create ML model entry
     */
    private function getOrCreateModel(string $modelName): ?int
    {
        try {
            $model = MLModels::firstOrCreate(
                ['model_name' => $modelName],
                [
                    'model_version' => '1.0',
                    'model_type' => 'prediction',
                    'is_active' => true,
                ]
            );

            return $model->id;
        } catch (Exception $e) {
            Log::error("Failed to create ML model entry: {$e->getMessage()}");

            return null;
        }
    }
}
