<?php

use App\Jobs\GenerateDailyFarmSummaries;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyFarmSummaries(now()->toDateString()))
    ->dailyAt('23:55')
    ->name('generate-daily-farm-summaries');
