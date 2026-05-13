<?php

namespace App\Jobs;

use App\Services\FastAPIIntegration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async job for processing ML predictions from FastAPI
 *
 * Handles single and batch predictions asynchronously to avoid blocking
 * frontend requests. Supports caching, retry logic, and webhook notifications.
 */
class AsyncPredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        private string $predictionType,
        private ?int $penId,
        private array $options,
        private array $webhookUrls
    ) {
        $this->queue = 'predictions';
    }

    /**
     * Execute the job
     */
    public function handle(FastAPIIntegration $fastapi): void
    {
        Log::info('AsyncPredictionJob: Starting prediction', [
            'type' => $this->predictionType,
            'pen_id' => $this->penId,
        ]);

        try {
            $result = match ($this->predictionType) {
                'feed_recommendation' => $this->handleFeedRecommendation($fastapi),
                'weight_trend' => $this->handleWeightTrend($fastapi),
                'pen_status' => $this->handlePenStatus($fastapi),
                default => throw new \Exception("Unknown prediction type: {$this->predictionType}"),
            };

            if (! $result['success']) {
                throw new \Exception($result['error'] ?? 'Prediction failed');
            }

            Log::info('AsyncPredictionJob: Prediction completed', [
                'type' => $this->predictionType,
                'pen_id' => $this->penId,
            ]);
        } catch (\Exception $e) {
            Log::error('AsyncPredictionJob: Prediction failed', [
                'type' => $this->predictionType,
                'pen_id' => $this->penId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle feed recommendation prediction
     */
    private function handleFeedRecommendation(FastAPIIntegration $fastapi): array
    {
        if ($this->penId) {
            return $fastapi->predictFeedRecommendation($this->penId, $this->options, false, false);
        }

        $penIds = $this->options['pen_ids'] ?? [];

        return $fastapi->batchPredictFeedRecommendation($penIds, false);
    }

    /**
     * Handle weight trend prediction
     */
    private function handleWeightTrend(FastAPIIntegration $fastapi): array
    {
        if ($this->penId) {
            return $fastapi->predictWeightTrend($this->penId, $this->options, false, false);
        }

        $penIds = $this->options['pen_ids'] ?? [];

        return $fastapi->batchPredictWeightTrend($penIds, false);
    }

    /**
     * Handle pen status prediction
     */
    private function handlePenStatus(FastAPIIntegration $fastapi): array
    {
        if ($this->penId) {
            return $fastapi->predictPenStatus($this->penId, $this->options, false, false);
        }

        $penIds = $this->options['pen_ids'] ?? [];

        return $fastapi->batchPredictPenStatus($penIds, false);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AsyncPredictionJob: Job failed after retries', [
            'type' => $this->predictionType,
            'pen_id' => $this->penId,
            'error' => $exception->getMessage(),
        ]);
    }
}
