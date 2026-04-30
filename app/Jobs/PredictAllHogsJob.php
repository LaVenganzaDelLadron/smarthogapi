<?php

namespace App\Jobs;

use App\Services\PredictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PredictAllHogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Set the queue for this job
        $this->queue = 'predictions';
    }

    /**
     * Execute the job.
     */
    public function handle(PredictionService $service): void
    {
        Log::info('PredictAllHogsJob: Starting batch hog health predictions');

        try {
            $result = $service->predictAllHogs();

            Log::info('PredictAllHogsJob: Batch predictions completed', [
                'total' => $result['total_hogs'] ?? 0,
                'successful' => $result['successful_predictions'] ?? 0,
                'failed' => $result['failed_predictions'] ?? 0,
            ]);

            // Publish completion event to Redis Pub/Sub
            Redis::publish('predictions-completed', json_encode([
                'timestamp' => now()->toIso8601String(),
                'result' => $result,
            ]));
        } catch (\Exception $e) {
            Log::error('PredictAllHogsJob: Batch prediction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Publish failure event
            Redis::publish('predictions-completed', json_encode([
                'timestamp' => now()->toIso8601String(),
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }
}
