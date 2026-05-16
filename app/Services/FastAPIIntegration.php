<?php

namespace App\Services;

use App\Jobs\AsyncPredictionJob;
use App\Models\FeedingPredictions;
use App\Models\Hogpens;
use App\Models\Hogs;
use App\Models\MLModels;
use App\Services\FastApi\FastApiPayloadFactory;
use App\Services\FastApi\FastApiResponseNormalizer;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FastAPIIntegration
{
    private const DEFAULT_TIMEOUT_SECONDS = 30;

    private const HEALTH_CHECK_CACHE_MINUTES = 5;

    private const PREDICTION_CACHE_HOURS = 24;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MILLISECONDS = 250;

    private string $baseUrl;

    private int $timeoutSeconds;

    private int $connectTimeoutSeconds;

    /**
     * @var array<int, string>
     */
    private array $webhookUrls;

    public function __construct(
        private FastApiPayloadFactory $payloadFactory,
        private FastApiResponseNormalizer $responseNormalizer
    ) {
        $this->baseUrl = rtrim((string) config('services.fastapi.url', 'http://localhost:5000'), '/');
        $this->timeoutSeconds = (int) config('services.fastapi.timeout', self::DEFAULT_TIMEOUT_SECONDS);
        $this->connectTimeoutSeconds = (int) config('services.fastapi.connect_timeout', 5);
        $this->webhookUrls = array_values(array_filter(config('services.fastapi.webhooks', [])));
    }

    public function predictFeedRecommendation(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        return $this->runPenPrediction('feed_recommendation', $penId, '/predict/feed-recommendation', $overrides, $async, $useCache);
    }

    public function predictWeightTrend(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        return $this->runPenPrediction('weight_trend', $penId, '/predict/weight-trend', $overrides, $async, $useCache);
    }

    public function predictPenStatus(
        int $penId,
        array $overrides = [],
        bool $async = false,
        bool $useCache = true
    ): array {
        return $this->runPenPrediction('pen_status', $penId, '/predict/pen-status-classification', $overrides, $async, $useCache);
    }

    /**
     * @param  array<int, int>  $penIds
     */
    public function batchPredictFeedRecommendation(array $penIds, bool $async = false): array
    {
        return $this->runBatchPenPrediction('feed_recommendation', $penIds, '/predict/batch/feed-recommendation', $async);
    }

    /**
     * @param  array<int, int>  $penIds
     */
    public function batchPredictWeightTrend(array $penIds, bool $async = false): array
    {
        return $this->runBatchPenPrediction('weight_trend', $penIds, '/predict/batch/weight-trend', $async);
    }

    /**
     * @param  array<int, int>  $penIds
     */
    public function batchPredictPenStatus(array $penIds, bool $async = false): array
    {
        return $this->runBatchPenPrediction('pen_status', $penIds, '/predict/batch/pen-status-classification', $async);
    }

    public function predictHogHealth(int $hogId): array
    {
        try {
            $hog = Hogs::query()->with(['hogpen.farm', 'hogDailyRecords'])->findOrFail($hogId);
            $payload = $this->payloadFactory->makeHogHealthPayload($hog);
            $response = $this->sendRequest('POST', '/predict/hog-health', $payload, 10);

            return [
                'success' => true,
                'data' => $this->responseNormalizer->normalizeLegacyPrediction($response->json()),
                'cached' => false,
                'ml_service_status' => 'healthy',
            ];
        } catch (Throwable $exception) {
            Log::error('FastAPI hog health prediction failed', [
                'hog_id' => $hogId,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Prediction failed',
                'error' => $exception->getMessage(),
                'ml_service_status' => 'unavailable',
                'status' => $this->statusCodeForException($exception),
            ];
        }
    }

    public function predictFeedDemand(int $farmId): array
    {
        return $this->runLegacyPrediction('/predict/feed-demand', ['farm_id' => $farmId], 'feed demand');
    }

    public function predictWeightGrowth(int $hogId): array
    {
        return $this->runLegacyPrediction('/predict/weight-growth', ['hog_id' => $hogId], 'weight growth');
    }

    public function predictOutbreakRisk(int $penId): array
    {
        return $this->runLegacyPrediction('/predict/outbreak-risk', ['pen_id' => $penId], 'outbreak risk');
    }

    public function trainFastValidation(array $options = []): array
    {
        return $this->runPassthroughRequest('POST', '/train/fast-validation', array_merge(['mode' => 'fast'], $options));
    }

    public function trainFull(array $options = []): array
    {
        return $this->runPassthroughRequest('POST', '/train/full-training', array_merge(['mode' => 'full'], $options), 60);
    }

    public function twinStartSimulation(array $options = []): array
    {
        return $this->runPassthroughRequest('POST', '/twin/start-simulation', $options);
    }

    public function twinIngestEvent(array $event): array
    {
        return $this->runPassthroughRequest('POST', '/twin/ingest-event', $event);
    }

    public function twinCurrentState(): array
    {
        return $this->runPassthroughRequest('GET', '/twin/current-state');
    }

    public function twinLiveEvents(): array
    {
        return $this->runPassthroughRequest('GET', '/twin/live-events');
    }

    public function healthCheck(): bool
    {
        $cacheKey = 'fastapi:health';
        $cached = Cache::get($cacheKey);

        if (is_bool($cached)) {
            return $cached;
        }

        try {
            $response = $this->sendRequest('GET', '/health', [], 5);
            $payload = $response->json();
            $isHealthy = $response->successful() && data_get($payload, 'status') === 'ok';

            Cache::put($cacheKey, $isHealthy, now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            return $isHealthy;
        } catch (Throwable $exception) {
            Log::warning('FastAPI health check failed', [
                'error' => $exception->getMessage(),
            ]);

            Cache::put($cacheKey, false, now()->addMinutes(self::HEALTH_CHECK_CACHE_MINUTES));

            return false;
        }
    }

    private function runPenPrediction(
        string $predictionType,
        int $penId,
        string $endpoint,
        array $overrides,
        bool $async,
        bool $useCache
    ): array {
        try {
            if ($useCache) {
                $cached = $this->getCachedPrediction($predictionType, $penId);
                if ($cached !== null) {
                    return [
                        'success' => true,
                        'message' => 'Prediction served from cache',
                        'data' => $cached,
                    ];
                }
            }

            if ($async) {
                AsyncPredictionJob::dispatch($predictionType, $penId, $overrides, $this->webhookUrls);

                return [
                    'success' => true,
                    'message' => 'Prediction queued for processing',
                ];
            }

            $pen = $this->loadPenForPrediction($penId);
            $payload = $this->payloadFactory->makePenPredictionPayload($pen, $overrides);
            $response = $this->sendRequest('POST', $endpoint, $payload);
            $normalized = $this->responseNormalizer->normalizePrediction($predictionType, $response->json());

            $this->cachePrediction($predictionType, $penId, $normalized);

            $predictionId = null;
            if ($predictionType === 'feed_recommendation') {
                $predictionId = $this->storeFeedRecommendation($penId, $normalized);
            }

            return [
                'success' => true,
                'prediction_id' => $predictionId,
                'data' => $normalized,
            ];
        } catch (Throwable $exception) {
            Log::error('FastAPI pen prediction failed', [
                'prediction_type' => $predictionType,
                'pen_id' => $penId,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $this->messageForPredictionFailure($predictionType),
                'error' => $exception->getMessage(),
                'status' => $this->statusCodeForException($exception),
            ];
        }
    }

    /**
     * @param  array<int, int>  $penIds
     */
    private function runBatchPenPrediction(string $predictionType, array $penIds, string $endpoint, bool $async): array
    {
        try {
            if ($async) {
                return $this->dispatchAsyncBatchPrediction($predictionType, $penIds);
            }

            $pens = Hogpens::query()
                ->with(['hogs.hogDailyRecords', 'farm.hogpens', 'feeders.iotDevice', 'feedingSchedules'])
                ->whereIn('id', $penIds)
                ->get()
                ->keyBy('id');

            $items = [];
            $orderedPenIds = [];

            foreach ($penIds as $penId) {
                $pen = $pens->get($penId);
                if ($pen === null) {
                    continue;
                }

                $items[] = $this->payloadFactory->makePenPredictionPayload($pen);
                $orderedPenIds[] = $penId;
            }

            if ($items === []) {
                throw new DomainException('No valid pens were available for batch prediction.');
            }

            $response = $this->sendRequest('POST', $endpoint, ['items' => $items]);
            $normalized = $this->responseNormalizer->normalizeBatchPrediction($predictionType, $response->json());

            foreach ($normalized['results'] as $index => $result) {
                $penId = $orderedPenIds[$index] ?? null;
                if ($penId === null) {
                    continue;
                }

                $this->cachePrediction($predictionType, $penId, $result);

                if ($predictionType === 'feed_recommendation') {
                    $this->storeFeedRecommendation($penId, $result);
                }
            }

            return [
                'success' => true,
                'count' => count($normalized['results']),
                'data' => $normalized,
            ];
        } catch (Throwable $exception) {
            Log::error('FastAPI batch prediction failed', [
                'prediction_type' => $predictionType,
                'pen_ids' => $penIds,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Prediction failed',
                'error' => $exception->getMessage(),
                'status' => $this->statusCodeForException($exception),
            ];
        }
    }

    private function runLegacyPrediction(string $endpoint, array $payload, string $label): array
    {
        try {
            $response = $this->sendRequest('POST', $endpoint, $payload, 10);

            return [
                'success' => true,
                'data' => $this->responseNormalizer->normalizeLegacyPrediction($response->json()),
                'cached' => false,
            ];
        } catch (Throwable $exception) {
            Log::error("FastAPI {$label} request failed", [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => ucfirst($label).' prediction failed',
                'error' => $exception->getMessage(),
                'status' => $this->statusCodeForException($exception),
            ];
        }
    }

    private function runPassthroughRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        ?int $timeoutSeconds = null
    ): array {
        try {
            $response = $this->sendRequest($method, $endpoint, $payload, $timeoutSeconds);

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (Throwable $exception) {
            Log::error('FastAPI passthrough request failed', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Upstream request failed',
                'error' => $exception->getMessage(),
                'status' => $this->statusCodeForException($exception),
            ];
        }
    }

    private function sendRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        ?int $timeoutSeconds = null
    ): Response {
        $url = $this->baseUrl.$endpoint;
        $timeoutSeconds ??= $this->timeoutSeconds;

        try {
            $request = Http::acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($timeoutSeconds)
                ->retry(
                    self::MAX_RETRIES,
                    self::RETRY_DELAY_MILLISECONDS,
                    function (Throwable $exception): bool {
                        return $exception instanceof ConnectionException
                            || ($exception instanceof RequestException && $exception->response !== null && $exception->response->serverError());
                    }
                );

            return match (strtoupper($method)) {
                'GET' => $request->get($url, $payload)->throw(),
                'POST' => $request->post($url, $payload)->throw(),
                default => throw new DomainException("Unsupported HTTP method [{$method}]."),
            };
        } catch (ConnectionException|RequestException $exception) {
            $statusCode = $exception instanceof RequestException && $exception->response !== null
                ? $exception->response->status()
                : null;

            Log::warning('FastAPI upstream request failed', [
                'method' => strtoupper($method),
                'url' => $url,
                'status' => $statusCode,
                'payload' => $payload,
                'error' => $exception->getMessage(),
                'response' => $exception instanceof RequestException && $exception->response !== null
                    ? mb_substr($exception->response->body(), 0, 500)
                    : null,
            ]);

            throw $exception;
        }
    }

    private function loadPenForPrediction(int $penId): Hogpens
    {
        return Hogpens::query()
            ->with(['hogs.hogDailyRecords', 'farm.hogpens', 'feeders.iotDevice', 'feedingSchedules'])
            ->findOrFail($penId);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function storeFeedRecommendation(int $penId, array $normalized): int
    {
        $prediction = FeedingPredictions::query()->create([
            'hog_pen_id' => $penId,
            'ml_model_id' => $this->getOrCreateModelId((string) ($normalized['model_used'] ?? 'feed_recommendation')),
            'predicted_feed_amount' => $normalized['predicted_feed_amount'],
            'confidence_score' => $normalized['confidence_score'],
            'model_used' => $normalized['model_used'],
            'confidence_level' => $normalized['confidence_level'],
            'confidence_reason' => $normalized['confidence_reason'],
            'feed_recommendation' => $normalized['feed_recommendation'],
            'feed_totals' => $normalized['feed_totals'],
            'weight_trend' => $normalized['weight_trend'],
            'pen_status' => $normalized['pen_status'],
            'warnings' => $normalized['warnings'],
            'alerts' => $normalized['alerts'],
            'suggestions' => $normalized['suggestions'],
            'fastapi_response' => $normalized['raw'],
            'predicted_at' => now(),
        ]);

        return (int) $prediction->id;
    }

    private function getOrCreateModelId(string $modelName): int
    {
        $model = MLModels::query()->firstOrCreate(
            ['model_name' => $modelName],
            [
                'version' => 'unknown',
                'accuracy_score' => 0,
            ]
        );

        return (int) $model->id;
    }

    private function getCachedPrediction(string $predictionType, int $penId): ?array
    {
        $cached = Cache::get($this->predictionCacheKey($predictionType, $penId));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $prediction
     */
    private function cachePrediction(string $predictionType, int $penId, array $prediction): void
    {
        Cache::put(
            $this->predictionCacheKey($predictionType, $penId),
            $prediction,
            now()->addHours(self::PREDICTION_CACHE_HOURS)
        );
    }

    private function predictionCacheKey(string $predictionType, int $penId): string
    {
        return "fastapi:prediction:{$predictionType}:pen:{$penId}";
    }

    /**
     * @param  array<int, int>  $penIds
     */
    private function dispatchAsyncBatchPrediction(string $predictionType, array $penIds): array
    {
        AsyncPredictionJob::dispatch($predictionType, null, ['pen_ids' => $penIds], $this->webhookUrls);

        return [
            'success' => true,
            'count' => count($penIds),
            'message' => 'Batch prediction queued for processing',
            'data' => [],
        ];
    }

    private function statusCodeForException(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof DomainException => 422,
            $exception instanceof ConnectionException => 504,
            $exception instanceof RequestException && $exception->response !== null && $exception->response->serverError() => 502,
            $exception instanceof RequestException && $exception->response !== null => $exception->response->status(),
            default => 500,
        };
    }

    private function messageForPredictionFailure(string $predictionType): string
    {
        return match ($predictionType) {
            'feed_recommendation' => 'Feed recommendation failed',
            'weight_trend' => 'Weight trend prediction failed',
            'pen_status' => 'Pen status prediction failed',
            default => 'Prediction failed',
        };
    }
}
