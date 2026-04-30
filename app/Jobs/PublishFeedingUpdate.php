<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PublishFeedingUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $jobId,
        private string $status,
        private array $data = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $payload = [
                'job_id' => $this->jobId,
                'status' => $this->status,
                'timestamp' => now()->toIso8601String(),
                'data' => $this->data,
            ];

            // Publish to Redis Pub/Sub channel
            Redis::publish('feeding-jobs', json_encode($payload));

            Log::info('PublishFeedingUpdate: Message published', [
                'job_id' => $this->jobId,
                'status' => $this->status,
            ]);
        } catch (\Exception $e) {
            Log::error('PublishFeedingUpdate: Failed to publish', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
