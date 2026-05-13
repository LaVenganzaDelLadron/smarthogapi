<?php

namespace App\Services;

use App\Jobs\AsyncPredictionJob;
use App\Models\FeedingPredictions;
use App\Models\Hogpens;
use App\Models\MLModels;
use App\Models\WebhookLog;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrates with the FastAPI ML service for predictions
 *
 * Features:
 * - Feed recommendations (single & batch)
 * - Weight trend predictions (single & batch)
 * - Pen status classification (single & batch)
 * - Model training endpoints
 * - Digital twin simulation
 * - Intelligent caching layer
 * - Retry logic with exponential backoff
 * - Webhook notifications
 * - Async queue support
 */
class FastAPIIntegration
{
    private const TIMEOUT_SECONDS = 30;

    private const HEALTH_CHECK_CACHE_MINUTES = 5;

    private const PREDICTION_CACHE_HOURS = 24;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_SECONDS = 2;

    private string $baseUrl;

    private array $webhookUrls = [];

    public function __construct()
    {
        $this->baseUrl = config('services.fastapi.url') ?? 'http://localhost:5000';
        $this->webhookUrls = config('services.fastapi.webhooks', []);
    }

    /**
     * Predict feed recommendation for a hog pen
     *
     * @param  array  $overrides  Optional field overrides
     * @param  bool  $async  Run prediction asynchronously
     * @param  bool  $useCache  Check cache before requesting
     * @return array Feed recommendation with full response structure
     */
    public function predictFeedRecommendation(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        try {
            // Check cache first if enabled
            if ($useCache) {
                $cached = $this->getCachedPrediction('feed_recommendation', $penId);
                if ($cached) {
                    Log::info("Feed recommendation cache hit for pen {$penId}");

                    return $cached;
                }
            }

            // Dispatch async job if requested
            if ($async) {
                AsyncPredictionJob::dispatch(
                    'feed_recommendation',
                    $penId,
                    $overrides,
                    $this->webhookUrls
                );

                return [
                    'success' => true,
                    'message' => 'Prediction queued for processing',
                    'job_id' => uniqid(),
                ];
            }

            $pen = Hogpens::with('hogs', 'farm', 'feeder')->findOrFail($penId);
            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI feed recommendation for pen {$penId}");

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/feed-recommendation',
                $payload
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

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

            // Cache the result
            $this->cachePrediction('feed_recommendation', $penId, $prediction);

            // Send webhook notification
            $this->sendWebhook('prediction.feed_recommendation.completed', [
                'prediction_id' => $prediction->id,
                'pen_id' => $penId,
                'confidence_score' => $prediction->confidence_score,
            ]);

            return [
                'success' => true,
                'prediction_id' => $prediction->id,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Feed recommendation prediction failed for pen {$penId}: {$e->getMessage()}");

            $this->sendWebhook('prediction.feed_recommendation.failed', [
                'pen_id' => $penId,
                'error' => $e->getMessage(),
            ]);

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
     * @param  bool  $async  Run prediction asynchronously
     * @param  bool  $useCache  Check cache before requesting
     * @return array Weight trend prediction with confidence metrics
     */
    public function predictWeightTrend(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        try {
            if ($useCache) {
                $cached = $this->getCachedPrediction('weight_trend', $penId);
                if ($cached) {
                    Log::info("Weight trend cache hit for pen {$penId}");

                    return $cached;
                }
            }

            if ($async) {
                AsyncPredictionJob::dispatch(
                    'weight_trend',
                    $penId,
                    $overrides,
                    $this->webhookUrls
                );

                return [
                    'success' => true,
                    'message' => 'Weight trend prediction queued',
                    'job_id' => uniqid(),
                ];
            }

            $pen = Hogpens::with('hogs', 'farm')->findOrFail($penId);
            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI weight trend prediction for pen {$penId}");

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/weight-trend',
                $payload
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

            Log::info("Weight trend prediction computed for pen {$penId}", [
                'confidence' => $data['confidence'] ?? 'unknown',
            ]);

            // Cache result
            $this->cachePrediction('weight_trend', $penId, $data);

            // Send webhook
            $this->sendWebhook('prediction.weight_trend.completed', [
                'pen_id' => $penId,
                'confidence' => $data['confidence'] ?? null,
            ]);

            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error("Weight trend prediction failed: {$e->getMessage()}");

            $this->sendWebhook('prediction.weight_trend.failed', [
                'pen_id' => $penId,
                'error' => $e->getMessage(),
            ]);

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
     * @param  bool  $async  Run prediction asynchronously
     * @param  bool  $useCache  Check cache before requesting
     * @return array Pen status with classification confidence
     */
    public function predictPenStatus(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        try {
            if ($useCache) {
                $cached = $this->getCachedPrediction('pen_status', $penId);
                if ($cached) {
                    Log::info("Pen status cache hit for pen {$penId}");

                    return $cached;
                }
            }

            if ($async) {
                AsyncPredictionJob::dispatch(
                    'pen_status',
                    $penId,
                    $overrides,
                    $this->webhookUrls
                );

                return [
                    'success' => true,
                    'message' => 'Pen status prediction queued',
                    'job_id' => uniqid(),
                ];
            }

            $pen = Hogpens::with('hogs', 'farm')->findOrFail($penId);
            $payload = $this->buildFeedRequestPayload($pen, $overrides);

            Log::info("Calling FastAPI pen status classification for pen {$penId}");

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/pen-status-classification',
                $payload
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

            // Cache result
            $this->cachePrediction('pen_status', $penId, $data);

            // Send webhook
            $this->sendWebhook('prediction.pen_status.completed', [
                'pen_id' => $penId,
                'status' => $data['status'] ?? null,
            ]);

            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error("Pen status prediction failed: {$e->getMessage()}");

            $this->sendWebhook('prediction.pen_status.failed', [
                'pen_id' => $penId,
                'error' => $e->getMessage(),
            ]);

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
     * @param  bool  $async  Run asynchronously
     * @return array Results for all pens
     */
    public function batchPredictFeedRecommendation(array $penIds, bool $async = false): array
    {
        if ($async) {
            return $this->dispatchAsyncBatchPrediction('feed_recommendation', $penIds);
        }

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
            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/batch/feed-recommendation',
                ['items' => $items]
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];
            $results = $data['results'] ?? [];

            foreach ($results as $index => $result) {
                if (isset($penIds[$index])) {
                    try {
                        $prediction = FeedingPredictions::create([
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

                        $this->cachePrediction('feed_recommendation', $penIds[$index], $prediction);
                    } catch (Exception $e) {
                        Log::error("Failed to store batch prediction for pen {$penIds[$index]}: {$e->getMessage()}");
                    }
                }
            }

            $this->sendWebhook('prediction.batch_feed_recommendation.completed', [
                'total' => count($results),
                'pen_ids' => $penIds,
            ]);

            return [
                'success' => true,
                'count' => count($results),
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Batch feed prediction failed: {$e->getMessage()}");

            $this->sendWebhook('prediction.batch_feed_recommendation.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch predict weight trends for multiple pens
     *
     * @param  array  $penIds  Array of pen IDs
     * @param  bool  $async  Run asynchronously
     * @return array Weight trend results for all pens
     */
    public function batchPredictWeightTrend(array $penIds, bool $async = false): array
    {
        if ($async) {
            return $this->dispatchAsyncBatchPrediction('weight_trend', $penIds);
        }

        $items = [];

        foreach ($penIds as $penId) {
            $pen = Hogpens::with('hogs', 'farm')->find($penId);
            if ($pen) {
                $items[] = $this->buildFeedRequestPayload($pen);
            }
        }

        if (empty($items)) {
            return ['success' => false, 'error' => 'No valid pens found'];
        }

        try {
            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/batch/weight-trend',
                ['items' => $items]
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];
            $results = $data['results'] ?? [];

            foreach ($results as $index => $result) {
                if (isset($penIds[$index])) {
                    $this->cachePrediction('weight_trend', $penIds[$index], $result);
                }
            }

            $this->sendWebhook('prediction.batch_weight_trend.completed', [
                'total' => count($results),
                'pen_ids' => $penIds,
            ]);

            return [
                'success' => true,
                'count' => count($results),
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Batch weight trend prediction failed: {$e->getMessage()}");

            $this->sendWebhook('prediction.batch_weight_trend.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch predict pen status for multiple pens
     *
     * @param  array  $penIds  Array of pen IDs
     * @param  bool  $async  Run asynchronously
     * @return array Pen status results for all pens
     */
    public function batchPredictPenStatus(array $penIds, bool $async = false): array
    {
        if ($async) {
            return $this->dispatchAsyncBatchPrediction('pen_status', $penIds);
        }

        $items = [];

        foreach ($penIds as $penId) {
            $pen = Hogpens::with('hogs', 'farm')->find($penId);
            if ($pen) {
                $items[] = $this->buildFeedRequestPayload($pen);
            }
        }

        if (empty($items)) {
            return ['success' => false, 'error' => 'No valid pens found'];
        }

        try {
            $response = $this->callFastAPIWithRetry(
                'POST',
                '/predict/batch/pen-status-classification',
                ['items' => $items]
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];
            $results = $data['results'] ?? [];

            foreach ($results as $index => $result) {
                if (isset($penIds[$index])) {
                    $this->cachePrediction('pen_status', $penIds[$index], $result);
                }
            }

            $this->sendWebhook('prediction.batch_pen_status.completed', [
                'total' => count($results),
                'pen_ids' => $penIds,
            ]);

            return [
                'success' => true,
                'count' => count($results),
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Batch pen status prediction failed: {$e->getMessage()}");

            $this->sendWebhook('prediction.batch_pen_status.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run fast validation training cycle
     *
     * Quick model training/validation with subset of data for testing improvements
     *
     * @param  array  $options  Training options (e.g., learning_rate, epochs)
     * @return array Training report with model performance
     */
    public function trainFastValidation(array $options = []): array
    {
        try {
            Log::info('Starting fast validation training');

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/train/fast-validation',
                array_merge(['mode' => 'fast'], $options)
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

            Log::info('Fast validation training completed', [
                'training_rows' => $data['training_rows'] ?? 0,
                'accuracy' => $data['summary']['accuracy'] ?? null,
            ]);

            $this->sendWebhook('training.fast_validation.completed', $data);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Fast validation training failed: {$e->getMessage()}");

            $this->sendWebhook('training.fast_validation.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run full model training
     *
     * Complete model training on full dataset for production-ready models
     *
     * @param  array  $options  Training options
     * @return array Training report with model performance
     */
    public function trainFull(array $options = []): array
    {
        try {
            Log::info('Starting full model training');

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/train/full-training',
                array_merge(['mode' => 'full'], $options),
                60 // 60 second timeout for full training
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

            Log::info('Full model training completed', [
                'training_rows' => $data['training_rows'] ?? 0,
                'accuracy' => $data['summary']['accuracy'] ?? null,
                'artifact_dir' => $data['artifact_directory'] ?? null,
            ]);

            $this->sendWebhook('training.full.completed', $data);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Full model training failed: {$e->getMessage()}");

            $this->sendWebhook('training.full.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Start digital twin simulation
     *
     * Generates synthetic farm events for testing and development
     *
     * @param  array  $options  Simulation options (events_count, continuous, etc.)
     * @return array Simulation status
     */
    public function twinStartSimulation(array $options = []): array
    {
        try {
            Log::info('Starting digital twin simulation', $options);

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/twin/start-simulation',
                $options
            );

            if (! $response['success']) {
                return $response;
            }

            $data = $response['data'];

            $this->sendWebhook('twin.simulation.started', $data);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error("Digital twin simulation failed: {$e->getMessage()}");

            $this->sendWebhook('twin.simulation.failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ingest event into digital twin
     *
     * Ingests real or simulated farm events to update digital twin state
     *
     * @param  array  $event  Event data (sensor data, feeding events, etc.)
     * @return array Updated twin state
     */
    public function twinIngestEvent(array $event): array
    {
        try {
            Log::info('Ingesting event into digital twin', ['event_type' => $event['type'] ?? 'unknown']);

            $response = $this->callFastAPIWithRetry(
                'POST',
                '/twin/ingest-event',
                $event
            );

            if (! $response['success']) {
                return $response;
            }

            $this->sendWebhook('twin.event.ingested', [
                'event_type' => $event['type'] ?? null,
            ]);

            return [
                'success' => true,
                'data' => $response['data'],
            ];
        } catch (Exception $e) {
            Log::error("Digital twin event ingestion failed: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current digital twin state
     *
     * Retrieves the complete current state of the digital twin
     *
     * @return array Digital twin state
     */
    public function twinGetCurrentState(): array
    {
        try {
            $response = $this->callFastAPIWithRetry(
                'GET',
                '/twin/current-state',
                null
            );

            if (! $response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data'],
            ];
        } catch (Exception $e) {
            Log::error("Failed to get digital twin state: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get digital twin live events stream
     *
     * @return array Latest digital twin stream events
     */
    public function twinGetLiveEvents(): array
    {
        try {
            $response = $this->callFastAPIWithRetry(
                'GET',
                '/twin/live-events',
                null
            );

            if (! $response['success']) {
                return $response;
            }

            return [
                'success' => true,
                'data' => $response['data'],
            ];
        } catch (Exception $e) {
            Log::error("Failed to get digital twin events: {$e->getMessage()}");

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
     * Call FastAPI endpoint with retry logic and exponential backoff
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  mixed  $payload  Request payload
     * @param  int  $timeoutSeconds  Request timeout
     * @return array Success/error response
     */
    private function callFastAPIWithRetry(
        string $method,
        string $endpoint,
        $payload = null,
        int $timeoutSeconds = self::TIMEOUT_SECONDS
    ): array {
        $url = "{$this->baseUrl}{$endpoint}";
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $request = Http::timeout($timeoutSeconds);

                if ($method === 'GET') {
                    $response = $request->get($url);
                } else {
                    $response = $request->post($url, $payload ?? []);
                }

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json(),
                    ];
                }

                if ($response->status() >= 500) {
                    // Retry on server errors
                    $attempt++;
                    if ($attempt < self::MAX_RETRIES) {
                        $delay = self::RETRY_DELAY_SECONDS * pow(2, $attempt - 1);
                        Log::warning("FastAPI error {$response->status()}, retrying in {$delay}s (attempt {$attempt})");
                        sleep($delay);

                        continue;
                    }
                }

                throw new Exception("FastAPI error: HTTP {$response->status()} - {$response->body()}");
            } catch (Exception $e) {
                $attempt++;

                if ($attempt >= self::MAX_RETRIES) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                $delay = self::RETRY_DELAY_SECONDS * pow(2, $attempt - 1);
                Log::warning("FastAPI request failed, retrying in {$delay}s (attempt {$attempt}): {$e->getMessage()}");
                sleep($delay);
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded',
        ];
    }

    /**
     * Get cached prediction if available
     *
     * @param  string  $type  Prediction type (feed_recommendation, weight_trend, pen_status)
     * @param  int  $penId  Hog pen ID
     * @return array|null Cached prediction or null
     */
    private function getCachedPrediction(string $type, int $penId): ?array
    {
        $cacheKey = "prediction:{$type}:pen_{$penId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache a prediction result
     *
     * @param  string  $type  Prediction type
     * @param  int  $penId  Hog pen ID
     * @param  mixed  $data  Data to cache
     */
    private function cachePrediction(string $type, int $penId, $data): void
    {
        $cacheKey = "prediction:{$type}:pen_{$penId}";

        $result = [
            'success' => true,
            'data' => $data instanceof FeedingPredictions ? $data->toArray() : $data,
        ];

        Cache::put($cacheKey, $result, now()->addHours(self::PREDICTION_CACHE_HOURS));

        Log::debug("Cached prediction for {$type}:pen_{$penId}");
    }

    /**
     * Clear prediction cache for a pen
     *
     * @param  int  $penId  Hog pen ID
     * @param  string|null  $type  Specific prediction type or null for all
     */
    public function clearPredictionCache(int $penId, ?string $type = null): void
    {
        if ($type) {
            Cache::forget("prediction:{$type}:pen_{$penId}");
        } else {
            foreach (['feed_recommendation', 'weight_trend', 'pen_status'] as $predType) {
                Cache::forget("prediction:{$predType}:pen_{$penId}");
            }
        }

        Log::info("Cleared prediction cache for pen {$penId}");
    }

    /**
     * Send webhook notification
     *
     * @param  string  $event  Event name
     * @param  array  $data  Event data
     */
    private function sendWebhook(string $event, array $data): void
    {
        if (empty($this->webhookUrls)) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        foreach ($this->webhookUrls as $webhookUrl) {
            try {
                Http::timeout(10)->post($webhookUrl, $payload);

                // Log webhook call
                if (class_exists('App\Models\WebhookLog')) {
                    WebhookLog::create([
                        'url' => $webhookUrl,
                        'event' => $event,
                        'payload' => $payload,
                        'status' => 'sent',
                    ]);
                }
            } catch (Exception $e) {
                Log::warning("Webhook delivery failed for {$event}: {$e->getMessage()}");

                if (class_exists('App\Models\WebhookLog')) {
                    WebhookLog::create([
                        'url' => $webhookUrl,
                        'event' => $event,
                        'payload' => $payload,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Dispatch async batch prediction job
     *
     * @param  string  $type  Prediction type
     * @param  array  $penIds  Pen IDs
     */
    private function dispatchAsyncBatchPrediction(string $type, array $penIds): array
    {
        AsyncPredictionJob::dispatch($type, null, ['pen_ids' => $penIds], $this->webhookUrls);

        return [
            'success' => true,
            'message' => 'Batch prediction queued for async processing',
            'job_id' => uniqid(),
        ];
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
     * Extract feeding times from feeding schedule.
     *
     * FastAPI accepts variable-length `feeding_times`.
     */
    private function extractFeedingTimes(Hogpens $pen): array
    {
        $times = [];

        // Check if pen has any feeding schedules
        $feedingSchedules = $pen->feedingSchedule ?? [];
        if (empty($feedingSchedules)) {
            return $times;
        }

        // Get the first schedule (typically one per pen with new structure)
        $schedule = is_array($feedingSchedules)
            ? reset($feedingSchedules)
            : $feedingSchedules->first();

        if (! $schedule) {
            return $times;
        }

        // Priority 1: Use new JSON feeding_times array if available
        if ($schedule->feeding_times && is_array($schedule->feeding_times)) {
            $times = $schedule->feeding_times;
        } else {
            // Priority 2: Fallback to old structure with multiple time entries
            foreach ((is_array($feedingSchedules) ? $feedingSchedules : $feedingSchedules->all()) as $sched) {
                $t = $sched->time;
                if (! $t) {
                    continue;
                }

                // If it's a Carbon instance (typical), format directly.
                if (is_object($t) && method_exists($t, 'format')) {
                    $times[] = $t->format('H:i');
                } else {
                    // Fallback for string values
                    $times[] = date('H:i', strtotime((string) $t));
                }
            }
        }

        // Sort + unique
        $times = array_values(array_unique($times));
        sort($times);

        return $times;
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
