<?php

namespace App\Jobs;

use App\Services\Analytics\DailyFarmSummaryService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyFarmSummaries implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $reportDate = null,
        public ?int $farmId = null
    ) {}

    public function handle(DailyFarmSummaryService $dailyFarmSummaryService): void
    {
        $dailyFarmSummaryService->generateForDate(
            $this->reportDate ? Carbon::parse($this->reportDate) : now(),
            $this->farmId
        );
    }
}
