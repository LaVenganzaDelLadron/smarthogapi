<?php

namespace App\Http\Controllers;

use App\Jobs\PublishFeedingUpdate;
use App\Models\Feeders;
use App\Models\FeedingQueue;
use App\Services\FeedingQueueService;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FeedingQueueController extends Controller
{
    public function __construct(
        protected FeedingQueueService $service,
        protected MetricsService $metrics
    ) {}

    /**
     * Get next pending jobs for ESP32 with rate limiting.
     * POST /api/feeding-queue/next-job
     */
    public function nextJob(Request $request)
    {
        try {
            $validated = $request->validate([
                'feeder_id' => 'required|integer|exists:feeders,id',
                'max_jobs' => 'integer|min:1|max:10',
            ]);

            $feederId = $validated['feeder_id'];

            // Rate limiting: 100 requests per minute per ESP32
            $rateLimitKey = "esp32:{$feederId}:requests";
            if (Redis::incr($rateLimitKey) > 100) {
                if (Redis::ttl($rateLimitKey) === -1) {
                    Redis::expire($rateLimitKey, 60);
                }
                Log::warning("Rate limit exceeded for feeder {$feederId}");

                return response()->json([
                    'success' => false,
                    'message' => 'Rate limited. Too many requests.',
                ], 429);
            }

            // Auto-expire rate limit key after 60 seconds
            if (Redis::ttl($rateLimitKey) === -1) {
                Redis::expire($rateLimitKey, 60);
            }

            // Get next jobs
            $jobs = $this->service->getNextJobs(
                $feederId,
                $validated['max_jobs'] ?? 1
            );

            // Increment metrics
            $this->metrics->incrementFeedingAttempts($feederId);

            return response()->json([
                'success' => true,
                'jobs' => $jobs->toArray(),
                'count' => $jobs->count(),
            ], 200);
        } catch (\Exception $e) {
            $this->metrics->incrementErrors('feeding-queue-next-job');
            Log::error('FeedingQueueController nextJob error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch next job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get relay configuration for a feeder.
     * GET /api/feeders/{feeder_id}/relay-config
     */
    public function getRelayConfig(Feeders $feeder)
    {
        try {
            return response()->json(
                $this->service->getRelayConfig($feeder->id),
                200
            );
        } catch (\Exception $e) {
            $this->metrics->incrementErrors('relay-config');
            Log::error('FeedingQueueController getRelayConfig error', [
                'feeder_id' => $feeder->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve relay configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update job status after ESP32 execution with Pub/Sub notification.
     * PATCH /api/feeding-queue/{id}
     */
    public function update(Request $request, FeedingQueue $feedingQueue)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:processing,completed,skipped,error',
                'duration_seconds' => 'integer|min:0',
                'actual_feed_time' => 'date_format:Y-m-d H:i:s',
                'amount_dispensed' => 'numeric|min:0',
                'error_message' => 'string|max:255',
            ]);

            // Update job status
            $job = $this->service->updateJobStatus(
                $feedingQueue->id,
                $validated['status'],
                durationSeconds: $validated['duration_seconds'] ?? null,
                amountDispensed: $validated['amount_dispensed'] ?? null,
                errorMessage: $validated['error_message'] ?? null,
            );

            // Publish real-time update via Redis Pub/Sub
            PublishFeedingUpdate::dispatch(
                $feedingQueue->id,
                $validated['status'],
                [
                    'feeder_id' => $feedingQueue->feeder_id,
                    'duration_seconds' => $validated['duration_seconds'] ?? null,
                    'amount_dispensed' => $validated['amount_dispensed'] ?? null,
                ]
            );

            // Release feeder lock
            $this->service->releaseFeedingLock($feedingQueue->feeder_id);

            return response()->json([
                'success' => true,
                'message' => 'Feeding job updated successfully',
                'data' => $job,
            ], 200);
        } catch (\Exception $e) {
            $this->metrics->incrementErrors('feeding-queue-update');
            Log::error('FeedingQueueController update error', [
                'job_id' => $feedingQueue->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all jobs for debugging/monitoring.
     * GET /api/feeding-queue
     */
    public function index(Request $request)
    {
        try {
            $query = FeedingQueue::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('feeder_id')) {
                $query->where('feeder_id', $request->feeder_id);
            }

            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }

            return response()->json([
                'success' => true,
                'data' => $query->paginate(50),
            ], 200);
        } catch (\Exception $e) {
            Log::error('FeedingQueueController index error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding queue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific job.
     * GET /api/feeding-queue/{id}
     */
    public function show(FeedingQueue $feedingQueue)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $feedingQueue,
            ], 200);
        } catch (\Exception $e) {
            Log::error('FeedingQueueController show error', [
                'job_id' => $feedingQueue->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
