<?php

namespace App\Console\Commands;

use App\Models\PredictionCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearPredictionCacheCommand extends Command
{
    protected $signature = 'predictions:clear-cache
        {--expired : Only clear expired cache entries}';

    protected $description = 'Clear prediction cache';

    public function handle(): int
    {
        if ($this->option('expired')) {
            $cleared = PredictionCache::clearExpired();
            $this->info("Cleared {$cleared} expired cache entries");

            return 0;
        }

        // Clear all prediction caches from Redis
        Cache::tags(['predictions'])->flush();
        PredictionCache::truncate();

        $this->info('✓ All prediction caches cleared');

        return 0;
    }
}
