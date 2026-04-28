<?php

namespace App\Services\Analytics;

use App\Models\DailyFarmReports;
use App\Models\Farms;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyFarmSummaryService
{
    public function __construct(
        public DescriptiveAnalyticsService $descriptiveAnalyticsService
    ) {}

    public function generateForDate(?Carbon $reportDate = null, ?int $farmId = null): Collection
    {
        $summaryDate = ($reportDate ?? now())->copy()->startOfDay();

        $farms = Farms::query()
            ->when($farmId, fn ($query) => $query->whereKey($farmId))
            ->get(['id']);

        return $farms->map(function (Farms $farm) use ($summaryDate) {
            $summary = $this->descriptiveAnalyticsService->dailySummary($summaryDate, $farm->id);

            DailyFarmReports::query()->updateOrCreate(
                [
                    'farm_id' => $farm->id,
                    'report_date' => $summary['report_date'],
                ],
                [
                    'total_feed_consumed' => $summary['total_feed_consumed'],
                    'total_hogs' => $summary['total_hogs'],
                    'avg_weight' => $summary['avg_weight'],
                    'mortality_count' => $summary['mortality_count'],
                    'active_pens' => $summary['active_pens'],
                    'avg_temperature' => $summary['avg_temperature'],
                    'avg_humidity' => $summary['avg_humidity'],
                    'alerts_triggered' => $summary['alerts_triggered'],
                    'sick_hogs' => $summary['sick_hogs'],
                    'avg_weekly_weight_gain' => $summary['avg_weekly_weight_gain'],
                ]
            );

            return $summary + ['farm_id' => $farm->id];
        });
    }
}
