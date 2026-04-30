<?php

use App\Jobs\GenerateDailyFarmSummaries;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyFarmSummaries(now()->toDateString()))
    ->dailyAt('23:55')
    ->name('generate-daily-farm-summaries');

// Batch predict all hogs health daily at 2 AM
Schedule::command('predict:hog-health --all')
    ->dailyAt('02:00')
    ->name('batch-predict-hog-health')
    ->onSuccess(function () {
        Log::info('Batch hog health prediction scheduled task completed successfully');
    })
    ->onFailure(function () {
        Log::error('Batch hog health prediction scheduled task failed');
    });
