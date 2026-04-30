<?php

namespace App\Services;

use App\Models\FeederFeedTypeMapping;
use App\Models\Feeders;
use App\Models\FeedingQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FeedingQueueService
{
    /**
     * Get next pending feeding jobs for a feeder with Redis locking.
     */
    public function getNextJobs(int $feederId, int $maxJobs = 1): Collection
    {
        // Try to acquire Redis lock to prevent concurrent feeding
        $lockKey = "feeder:{$feederId}:processing";
        if (! Redis::set($lockKey, time(), 'NX', 'EX', 30)) {
            // Feeder already processing
            return collect();
        }

        try {
            $jobs = FeedingQueue::where('feeder_id', $feederId)
                ->where('status', 'pending')
                ->orderBy('scheduled_at', 'asc')
                ->limit($maxJobs)
                ->get()
                ->map(function (FeedingQueue $job) {
                    $mapping = FeederFeedTypeMapping::where('feeder_id', $job->feeder_id)
                        ->where('feed_type', $job->feed_type)
                        ->firstOrFail();

                    return [
                        'id' => $job->id,
                        'feed_type' => $job->feed_type,
                        'relay_pin' => $mapping->relay_pin,
                        'max_duration_seconds' => $mapping->max_duration_seconds,
                        'hog_pen_id' => $job->hog_pen_id,
                        'scheduled_at' => $job->scheduled_at,
                    ];
                });

            // Cache relay config for ESP32 (24-hour TTL)
            Cache::store('feeding_cache')->put(
                "feeder:{$feederId}:config",
                $this->getRelayConfig($feederId),
                now()->addHours(24)
            );

            return $jobs;
        } finally {
            // Note: Lock will auto-expire after 30 seconds via Redis EX parameter
        }
    }

    /**
     * Release feeder lock after job completion.
     */
    public function releaseFeedingLock(int $feederId): void
    {
        Redis::del("feeder:{$feederId}:processing");
    }

    /**
     * Update job status after execution.
     */
    public function updateJobStatus(
        int $jobId,
        string $status,
        ?int $durationSeconds = null,
        ?float $amountDispensed = null,
        ?string $errorMessage = null,
    ): FeedingQueue {
        $job = FeedingQueue::findOrFail($jobId);

        $data = [
            'status' => $status,
        ];

        if ($status === 'processing') {
            // Mark as started now
        } elseif ($status === 'completed') {
            $data['actual_feed_time'] = now();
            if ($durationSeconds) {
                $data['duration_seconds'] = $durationSeconds;
            }
            if ($amountDispensed) {
                $data['amount_dispensed'] = $amountDispensed;
            }
        } elseif ($status === 'error') {
            $data['error_message'] = $errorMessage;
            if ($durationSeconds) {
                $data['duration_seconds'] = $durationSeconds;
            }
        }

        $job->update($data);

        return $job;
    }

    /**
     * Create feeding queue entries from a schedule.
     * Called when hog transitions to new growth stage.
     */
    public function createFromSchedule(
        int $hogPenId,
        string $feedType,
        ?string $feederId = null,
    ): FeedingQueue {
        // Find the feeder for this pen
        if (! $feederId) {
            $feeder = Feeders::where('hog_pen_id', $hogPenId)->firstOrFail();
            $feederId = $feeder->id;
        }

        // Verify this feeder supports this feed type
        FeederFeedTypeMapping::where('feeder_id', $feederId)
            ->where('feed_type', $feedType)
            ->where('is_active', true)
            ->firstOrFail();

        return FeedingQueue::create([
            'feeder_id' => $feederId,
            'hog_pen_id' => $hogPenId,
            'feed_type' => $feedType,
            'scheduled_at' => now()->addMinutes(5),
            'status' => 'pending',
        ]);
    }

    /**
     * Get relay configuration for a feeder (for ESP32 caching).
     */
    public function getRelayConfig(int $feederId): array
    {
        $feeder = Feeders::findOrFail($feederId);

        $relays = FeederFeedTypeMapping::where('feeder_id', $feederId)
            ->where('is_active', true)
            ->get()
            ->map(function (FeederFeedTypeMapping $mapping) {
                return [
                    'feed_type' => $mapping->feed_type,
                    'relay_pin' => $mapping->relay_pin,
                    'max_duration_seconds' => $mapping->max_duration_seconds,
                ];
            });

        return [
            'feeder_id' => $feederId,
            'relays' => $relays->toArray(),
        ];
    }

    /**
     * Check for stalled jobs (pending > 1 hour) and error them out.
     */
    public function handleStalledJobs(): int
    {
        $stalledJobs = FeedingQueue::where('status', 'pending')
            ->where('scheduled_at', '<', now()->subHour())
            ->get();

        $count = 0;
        foreach ($stalledJobs as $job) {
            $this->updateJobStatus(
                $job->id,
                'error',
                errorMessage: 'Job stalled for >1 hour, skipping'
            );
            // Increment error counter in Redis
            Redis::incr('errors:stalled-jobs');
            $count++;
        }

        return $count;
    }

    /**
     * Detect timeout violations (motor ran too long).
     */
    public function checkTimeoutViolations(): Collection
    {
        return FeedingQueue::where('status', 'completed')
            ->where('duration_seconds', '>', 0)
            ->get()
            ->filter(function (FeedingQueue $job) {
                $mapping = FeederFeedTypeMapping::where('feeder_id', $job->feeder_id)
                    ->where('feed_type', $job->feed_type)
                    ->first();

                return $mapping && $job->duration_seconds > $mapping->max_duration_seconds;
            });
    }
}
