<?php

namespace App\Http\Controllers;

use App\Models\Feeders;
use App\Models\FeedingQueue;
use App\Services\FeedingQueueService;
use Illuminate\Http\Request;

class FeedingQueueController extends Controller
{
    public function __construct(protected FeedingQueueService $service) {}

    /**
     * Get next pending jobs for ESP32.
     * POST /api/feeding-queue/next-job
     */
    public function nextJob(Request $request)
    {
        $validated = $request->validate([
            'feeder_id' => 'required|integer|exists:feeders,id',
            'max_jobs' => 'integer|min:1|max:10',
        ]);

        $jobs = $this->service->getNextJobs(
            $validated['feeder_id'],
            $validated['max_jobs'] ?? 1
        );

        return response()->json([
            'jobs' => $jobs->toArray(),
            'count' => $jobs->count(),
        ]);
    }

    /**
     * Get relay configuration for a feeder.
     * GET /api/feeders/{feeder_id}/relay-config
     */
    public function getRelayConfig(Feeders $feeder)
    {
        return response()->json(
            $this->service->getRelayConfig($feeder->id)
        );
    }

    /**
     * Update job status after ESP32 execution.
     * PATCH /api/feeding-queue/{id}
     */
    public function update(Request $request, FeedingQueue $feedingQueue)
    {
        $validated = $request->validate([
            'status' => 'required|in:processing,completed,skipped,error',
            'duration_seconds' => 'integer|min:0',
            'actual_feed_time' => 'date_format:Y-m-d H:i:s',
            'amount_dispensed' => 'numeric|min:0',
            'error_message' => 'string|max:255',
        ]);

        $job = $this->service->updateJobStatus(
            $feedingQueue->id,
            $validated['status'],
            durationSeconds: $validated['duration_seconds'] ?? null,
            amountDispensed: $validated['amount_dispensed'] ?? null,
            errorMessage: $validated['error_message'] ?? null,
        );

        return response()->json($job);
    }

    /**
     * List all jobs for debugging/monitoring.
     * GET /api/feeding-queue
     */
    public function index(Request $request)
    {
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

        return $query->paginate(50);
    }

    /**
     * Show a specific job.
     * GET /api/feeding-queue/{id}
     */
    public function show(FeedingQueue $feedingQueue)
    {
        return response()->json($feedingQueue);
    }
}
