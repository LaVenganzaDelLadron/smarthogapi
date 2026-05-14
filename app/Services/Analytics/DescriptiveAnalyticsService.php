<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DescriptiveAnalyticsService
{
    private const HEALTHY_STATUSES = ['healthy', 'normal', 'stable', '1'];

    private const UNRESOLVED_ALERT_STATUSES = ['open', 'pending', 'unresolved'];

    public function dashboard(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$todayStart, $todayEnd] = $this->todayRange();

        $hogCount = DB::table('hogs')
            ->join('hog_pens', 'hog_pens.id', '=', 'hogs.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->count();

        $activePensCount = DB::table('hog_pens')
            ->when($farmId, fn ($query) => $query->where('farm_id', $farmId))
            ->where('status', 1)
            ->count();

        $feedUsedToday = (float) DB::table('feeding_logs')
            ->join('hog_pens', 'hog_pens.id', '=', 'feeding_logs.pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('feeding_logs.created_at', [$todayStart, $todayEnd])
            ->sum('feeding_logs.feed_amount_given');

        $environmentAggregate = DB::table('sensor_readings')
            ->join('sensors', 'sensors.id', '=', 'sensor_readings.sensor_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'sensors.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('sensor_readings.created_at', [$todayStart, $todayEnd])
            ->selectRaw('AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%temp%" THEN sensor_readings.value END) as avg_temperature')
            ->selectRaw('AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%humid%" THEN sensor_readings.value END) as avg_humidity')
            ->first();

        $alertsTriggeredToday = DB::table('alerts')
            ->when($farmId, fn ($query) => $query->where('farm_id', $farmId))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $sickHogCount = DB::table('hog_daily_records as hdr')
            ->join('hog_pens', 'hog_pens.id', '=', 'hdr.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereRaw($this->healthStatusSql('hdr.health_status').' NOT IN ('.$this->quotedList(self::HEALTHY_STATUSES).')')
            ->whereRaw('hdr.recorded_date = (select max(recorded_date) from hog_daily_records where hog_id = hdr.hog_id)')
            ->distinct()
            ->count('hdr.hog_id');

        return [
            'total_hog_count' => $hogCount,
            'active_pens_count' => $activePensCount,
            'feed_used_today' => round($feedUsedToday, 2),
            'average_farm_temperature_today' => round((float) ($environmentAggregate?->avg_temperature ?? 0), 2),
            'average_humidity_today' => round((float) ($environmentAggregate?->avg_humidity ?? 0), 2),
            'alerts_triggered_today' => $alertsTriggeredToday,
            'sick_hog_count' => $sickHogCount,
            'average_weekly_weight_gain' => round($this->averageWeeklyWeightGain($farmId), 2),
        ];
    }

    public function feedReport(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$startDate, $endDate] = $this->resolveDateRange($filters, 30);
        $limit = $this->resolveLimit($filters);

        $dailyUsage = DB::table('feeding_logs')
            ->join('hog_pens', 'hog_pens.id', '=', 'feeding_logs.pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('feeding_logs.created_at', [$startDate, $endDate])
            ->selectRaw('DATE(feeding_logs.created_at) as usage_date, ROUND(SUM(feeding_logs.feed_amount_given), 2) as total_feed_used')
            ->groupByRaw('DATE(feeding_logs.created_at)')
            ->orderBy('usage_date')
            ->get();

        $weeklyUsage = DB::table('feeding_logs')
            ->join('hog_pens', 'hog_pens.id', '=', 'feeding_logs.pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('feeding_logs.created_at', [$startDate, $endDate])
            ->selectRaw($this->weekGroupingExpression('feeding_logs.created_at').' as year_week, ROUND(SUM(feeding_logs.feed_amount_given), 2) as total_feed_used')
            ->groupByRaw($this->weekGroupingExpression('feeding_logs.created_at'))
            ->orderBy('year_week')
            ->get();

        $usageByPen = DB::table('feeding_logs')
            ->join('hog_pens', 'hog_pens.id', '=', 'feeding_logs.pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('feeding_logs.created_at', [$startDate, $endDate])
            ->selectRaw('hog_pens.id as pen_id, hog_pens.name as pen_name, ROUND(SUM(feeding_logs.feed_amount_given), 2) as total_feed_used')
            ->groupBy('hog_pens.id', 'hog_pens.name')
            ->orderByDesc('total_feed_used')
            ->get();

        $usageByHog = DB::table('hog_daily_records')
            ->join('hogs', 'hogs.id', '=', 'hog_daily_records.hog_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->selectRaw('hogs.id as hog_id, hogs.ear_tag_id, ROUND(SUM(hog_daily_records.feed_consumed), 2) as total_feed_consumed')
            ->groupBy('hogs.id', 'hogs.ear_tag_id')
            ->orderByDesc('total_feed_consumed')
            ->limit($limit)
            ->get();

        return [
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'daily_feed_usage' => $dailyUsage,
            'weekly_feed_usage' => $weeklyUsage,
            'feed_usage_by_pen' => $usageByPen,
            'feed_usage_by_hog' => $usageByHog,
            'highest_feed_consuming_pen' => $usageByPen->first(),
            'lowest_feed_intake_hogs' => $usageByHog->sortBy('total_feed_consumed')->values()->take($limit),
        ];
    }

    public function growthReport(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$startDate, $endDate] = $this->resolveDateRange($filters, 30);
        $limit = $this->resolveLimit($filters);

        $weightTrendRows = DB::table('hog_daily_records')
            ->join('hogs', 'hogs.id', '=', 'hog_daily_records.hog_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->selectRaw('hogs.id as hog_id, hogs.ear_tag_id, DATE(hog_daily_records.recorded_date) as recorded_date, ROUND(AVG(hog_daily_records.weight), 2) as average_weight')
            ->groupBy('hogs.id', 'hogs.ear_tag_id')
            ->groupByRaw('DATE(hog_daily_records.recorded_date)')
            ->orderBy('recorded_date')
            ->get();

        $weightTrendPerHog = $weightTrendRows
            ->groupBy('hog_id')
            ->map(function (Collection $rows) {
                $firstRow = $rows->first();

                return [
                    'hog_id' => $firstRow->hog_id,
                    'ear_tag_id' => $firstRow->ear_tag_id,
                    'trend' => $rows->map(fn ($row) => [
                        'date' => $row->recorded_date,
                        'average_weight' => (float) $row->average_weight,
                    ])->values(),
                ];
            })
            ->values()
            ->take($limit);

        $hogGrowthBase = DB::table('hog_daily_records')
            ->join('hogs', 'hogs.id', '=', 'hog_daily_records.hog_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->selectRaw('hog_daily_records.hog_id, hog_daily_records.hog_pen_id, hogs.ear_tag_id, ROUND(MAX(hog_daily_records.weight) - MIN(hog_daily_records.weight), 2) as weight_gain')
            ->groupBy('hog_daily_records.hog_id', 'hog_daily_records.hog_pen_id', 'hogs.ear_tag_id');

        $penGrowthComparison = DB::query()
            ->fromSub(clone $hogGrowthBase, 'hog_growth')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_growth.hog_pen_id')
            ->selectRaw('hog_pens.id as pen_id, hog_pens.name as pen_name, ROUND(AVG(hog_growth.weight_gain), 2) as average_weight_gain')
            ->groupBy('hog_pens.id', 'hog_pens.name')
            ->orderByDesc('average_weight_gain')
            ->get();

        $underperformingHogs = DB::query()
            ->fromSub(clone $hogGrowthBase, 'hog_growth')
            ->select('hog_id', 'ear_tag_id', 'weight_gain')
            ->orderBy('weight_gain')
            ->limit($limit)
            ->get();

        $fastestGrowingHogs = DB::query()
            ->fromSub(clone $hogGrowthBase, 'hog_growth')
            ->select('hog_id', 'ear_tag_id', 'weight_gain')
            ->orderByDesc('weight_gain')
            ->limit($limit)
            ->get();

        return [
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'weight_trend_per_hog' => $weightTrendPerHog,
            'average_weekly_weight_gain' => round($this->averageWeeklyWeightGain($farmId, $endDate), 2),
            'pen_growth_comparison' => $penGrowthComparison,
            'underperforming_hogs' => $underperformingHogs,
            'fastest_growing_hogs' => $fastestGrowingHogs,
        ];
    }

    public function environmentReport(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$startDate, $endDate] = $this->resolveDateRange($filters, 30);

        $dailyEnvironment = DB::table('sensor_readings')
            ->join('sensors', 'sensors.id', '=', 'sensor_readings.sensor_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'sensors.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('sensor_readings.created_at', [$startDate, $endDate])
            ->selectRaw('DATE(sensor_readings.created_at) as reading_date')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%temp%" THEN sensor_readings.value END), 2) as average_temperature')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%humid%" THEN sensor_readings.value END), 2) as average_humidity')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%gas%" THEN sensor_readings.value END), 2) as average_gas_level')
            ->groupByRaw('DATE(sensor_readings.created_at)')
            ->orderBy('reading_date')
            ->get();

        $unsafeConditionCounts = DB::table('sensor_readings')
            ->join('sensors', 'sensors.id', '=', 'sensor_readings.sensor_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'sensors.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('sensor_readings.created_at', [$startDate, $endDate])
            ->selectRaw('SUM(CASE WHEN LOWER(sensors.sensor_type) LIKE "%temp%" AND (sensor_readings.value < 18 OR sensor_readings.value > 30) THEN 1 ELSE 0 END) as unsafe_temperature_count')
            ->selectRaw('SUM(CASE WHEN LOWER(sensors.sensor_type) LIKE "%humid%" AND (sensor_readings.value < 55 OR sensor_readings.value > 80) THEN 1 ELSE 0 END) as unsafe_humidity_count')
            ->selectRaw('SUM(CASE WHEN LOWER(sensors.sensor_type) LIKE "%gas%" AND sensor_readings.value > 50 THEN 1 ELSE 0 END) as unsafe_gas_count')
            ->first();

        $penEnvironmentalComparison = DB::table('sensor_readings')
            ->join('sensors', 'sensors.id', '=', 'sensor_readings.sensor_id')
            ->join('hog_pens', 'hog_pens.id', '=', 'sensors.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('sensor_readings.created_at', [$startDate, $endDate])
            ->selectRaw('hog_pens.id as pen_id, hog_pens.name as pen_name')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%temp%" THEN sensor_readings.value END), 2) as average_temperature')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%humid%" THEN sensor_readings.value END), 2) as average_humidity')
            ->selectRaw('ROUND(AVG(CASE WHEN LOWER(sensors.sensor_type) LIKE "%gas%" THEN sensor_readings.value END), 2) as average_gas_level')
            ->selectRaw('SUM(CASE WHEN (LOWER(sensors.sensor_type) LIKE "%temp%" AND (sensor_readings.value < 18 OR sensor_readings.value > 30)) OR (LOWER(sensors.sensor_type) LIKE "%humid%" AND (sensor_readings.value < 55 OR sensor_readings.value > 80)) OR (LOWER(sensors.sensor_type) LIKE "%gas%" AND sensor_readings.value > 50) THEN 1 ELSE 0 END) as unsafe_condition_count')
            ->groupBy('hog_pens.id', 'hog_pens.name')
            ->orderBy('hog_pens.name')
            ->get();

        return [
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'daily_average_temperature' => $dailyEnvironment->map(fn ($row) => [
                'date' => $row->reading_date,
                'average_temperature' => (float) ($row->average_temperature ?? 0),
            ])->values(),
            'daily_average_humidity' => $dailyEnvironment->map(fn ($row) => [
                'date' => $row->reading_date,
                'average_humidity' => (float) ($row->average_humidity ?? 0),
            ])->values(),
            'gas_level_averages' => $dailyEnvironment->map(fn ($row) => [
                'date' => $row->reading_date,
                'average_gas_level' => (float) ($row->average_gas_level ?? 0),
            ])->values(),
            'unsafe_condition_counts' => [
                'temperature' => (int) ($unsafeConditionCounts?->unsafe_temperature_count ?? 0),
                'humidity' => (int) ($unsafeConditionCounts?->unsafe_humidity_count ?? 0),
                'gas' => (int) ($unsafeConditionCounts?->unsafe_gas_count ?? 0),
            ],
            'pen_environmental_comparison' => $penEnvironmentalComparison,
        ];
    }

    public function alertsReport(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$startDate, $endDate] = $this->resolveDateRange($filters, 30);

        $alertsBase = DB::table('alerts')
            ->when($farmId, fn ($query) => $query->where('alerts.farm_id', $farmId))
            ->whereBetween('alerts.created_at', [$startDate, $endDate]);

        $alertsByType = (clone $alertsBase)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        $alertsBySeverity = (clone $alertsBase)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->orderByDesc('total')
            ->get();

        $alertsByDate = (clone $alertsBase)
            ->selectRaw('DATE(created_at) as alert_date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('alert_date')
            ->get();

        $mostProblematicPen = (clone $alertsBase)
            ->join('hog_pens', 'hog_pens.id', '=', 'alerts.hog_pen_id')
            ->selectRaw('hog_pens.id as pen_id, hog_pens.name as pen_name, COUNT(*) as total_alerts')
            ->groupBy('hog_pens.id', 'hog_pens.name')
            ->orderByDesc('total_alerts')
            ->first();

        $totalUnresolvedAlerts = DB::table('alerts')
            ->when($farmId, fn ($query) => $query->where('alerts.farm_id', $farmId))
            ->whereRaw($this->healthStatusSql('alerts.status').' IN ('.$this->quotedList(self::UNRESOLVED_ALERT_STATUSES).')')
            ->count();

        return [
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'alerts_by_type' => $alertsByType,
            'alerts_by_severity' => $alertsBySeverity,
            'alerts_by_date' => $alertsByDate,
            'most_problematic_pen' => $mostProblematicPen,
            'total_unresolved_alerts' => $totalUnresolvedAlerts,
        ];
    }

    public function penRanking(array $filters = []): array
    {
        $farmId = $filters['farm_id'] ?? null;
        [$startDate, $endDate] = $this->resolveDateRange($filters, 30);

        $pens = DB::table('hog_pens')
            ->when($farmId, fn ($query) => $query->where('farm_id', $farmId))
            ->select('id', 'name', 'farm_id')
            ->get()
            ->keyBy('id');

        $hogGrowth = DB::table('hog_daily_records')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->selectRaw('hog_daily_records.hog_id, hog_daily_records.hog_pen_id, ROUND(MAX(hog_daily_records.weight) - MIN(hog_daily_records.weight), 2) as weight_gain, SUM(hog_daily_records.feed_consumed) as total_feed_consumed')
            ->groupBy('hog_daily_records.hog_id', 'hog_daily_records.hog_pen_id');

        $growthRateByPen = DB::query()
            ->fromSub(clone $hogGrowth, 'hog_growth')
            ->selectRaw('hog_pen_id, ROUND(AVG(weight_gain), 2) as growth_rate')
            ->groupBy('hog_pen_id')
            ->pluck('growth_rate', 'hog_pen_id');

        $feedEfficiencyByPen = DB::query()
            ->fromSub(clone $hogGrowth, 'hog_growth')
            ->selectRaw('hog_pen_id, ROUND(SUM(weight_gain) / NULLIF(SUM(total_feed_consumed), 0), 4) as feed_efficiency')
            ->groupBy('hog_pen_id')
            ->pluck('feed_efficiency', 'hog_pen_id');

        $alertsByPen = DB::table('alerts')
            ->join('hog_pens', 'hog_pens.id', '=', 'alerts.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('alerts.created_at', [$startDate, $endDate])
            ->selectRaw('alerts.hog_pen_id, COUNT(*) as total_alerts')
            ->groupBy('alerts.hog_pen_id')
            ->pluck('total_alerts', 'alerts.hog_pen_id');

        $healthyRatios = DB::table('hog_daily_records as hdr')
            ->join('hog_pens', 'hog_pens.id', '=', 'hdr.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereRaw('hdr.recorded_date = (select max(recorded_date) from hog_daily_records where hog_id = hdr.hog_id)')
            ->selectRaw('hdr.hog_pen_id, SUM(CASE WHEN '.$this->healthStatusSql('hdr.health_status').' IN ('.$this->quotedList(self::HEALTHY_STATUSES).') THEN 1 ELSE 0 END) as healthy_hogs, COUNT(*) as total_hogs')
            ->groupBy('hdr.hog_pen_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->hog_pen_id => $row->total_hogs > 0 ? round(($row->healthy_hogs / $row->total_hogs) * 100, 2) : 0,
            ]);

        $rankings = $pens->map(function ($pen) use ($growthRateByPen, $feedEfficiencyByPen, $alertsByPen, $healthyRatios) {
            $growthRate = (float) ($growthRateByPen[$pen->id] ?? 0);
            $feedEfficiency = (float) ($feedEfficiencyByPen[$pen->id] ?? 0);
            $alertCount = (int) ($alertsByPen[$pen->id] ?? 0);
            $healthyRatio = (float) ($healthyRatios[$pen->id] ?? 0);

            $score = round(($growthRate * 25) + ($feedEfficiency * 100) + ($healthyRatio * 0.5) - ($alertCount * 5), 2);

            return [
                'pen_id' => $pen->id,
                'pen_name' => $pen->name,
                'growth_rate' => round($growthRate, 2),
                'alerts_count' => $alertCount,
                'feed_efficiency' => round($feedEfficiency, 4),
                'healthy_hog_ratio' => round($healthyRatio, 2),
                'score' => $score,
            ];
        })->sortByDesc('score')->values()->map(function (array $ranking, int $index) {
            $ranking['rank'] = $index + 1;

            return $ranking;
        });

        return [
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'rankings' => $rankings,
        ];
    }

    public function dailySummary(Carbon $reportDate, ?int $farmId = null): array
    {
        $startDate = $reportDate->copy()->startOfDay();
        $endDate = $reportDate->copy()->endOfDay();

        $dashboard = $this->dashboard([
            'farm_id' => $farmId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        $averageWeight = DB::table('hog_daily_records')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->avg('hog_daily_records.weight');

        $mortalityCount = DB::table('hog_daily_records')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$startDate, $endDate])
            ->whereRaw($this->healthStatusSql('hog_daily_records.health_status').' IN (\'dead\', \'deceased\')')
            ->count();

        return [
            'report_date' => $startDate->toDateTimeString(),
            'total_feed_consumed' => $dashboard['feed_used_today'],
            'total_hogs' => $dashboard['total_hog_count'],
            'avg_weight' => round((float) ($averageWeight ?? 0), 2),
            'mortality_count' => round((float) $mortalityCount, 2),
            'active_pens' => $dashboard['active_pens_count'],
            'avg_temperature' => $dashboard['average_farm_temperature_today'],
            'avg_humidity' => $dashboard['average_humidity_today'],
            'alerts_triggered' => $dashboard['alerts_triggered_today'],
            'sick_hogs' => $dashboard['sick_hog_count'],
            'avg_weekly_weight_gain' => $dashboard['average_weekly_weight_gain'],
        ];
    }

    private function averageWeeklyWeightGain(?int $farmId = null, ?Carbon $anchorDate = null): float
    {
        $anchor = $anchorDate?->copy()->endOfDay() ?? now()->endOfDay();
        $start = $anchor->copy()->subDays(6)->startOfDay();

        $weeklyGain = DB::table('hog_daily_records')
            ->join('hog_pens', 'hog_pens.id', '=', 'hog_daily_records.hog_pen_id')
            ->when($farmId, fn ($query) => $query->where('hog_pens.farm_id', $farmId))
            ->whereBetween('hog_daily_records.recorded_date', [$start, $anchor])
            ->selectRaw('hog_daily_records.hog_id, MAX(hog_daily_records.weight) - MIN(hog_daily_records.weight) as weekly_gain')
            ->groupBy('hog_daily_records.hog_id');

        return (float) (DB::query()->fromSub($weeklyGain, 'weekly_growth')->avg('weekly_gain') ?? 0);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $filters, int $defaultDays): array
    {
        $endDate = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        $startDate = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : $endDate->copy()->subDays($defaultDays - 1)->startOfDay();

        return [$startDate, $endDate];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayRange(): array
    {
        return [now()->startOfDay(), now()->endOfDay()];
    }

    private function resolveLimit(array $filters, int $default = 5): int
    {
        return (int) ($filters['limit'] ?? $default);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quotedList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value) => DB::getPdo()->quote($value))
            ->implode(', ');
    }

    private function healthStatusSql(string $column): string
    {
        return 'LOWER(CAST('.$column.' AS CHAR))';
    }

    private function weekGroupingExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%W', {$column})"
            : "DATE_FORMAT({$column}, '%x-%v')";
    }
}
