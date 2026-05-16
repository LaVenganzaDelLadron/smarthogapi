<?php

namespace App\Services\FastApi;

use App\Models\FeedingSchedule;
use App\Models\Hogpens;
use App\Models\Hogs;
use DomainException;
use Illuminate\Support\Collection;

class FastApiPayloadFactory
{
    /**
     * Build the payload expected by the FastAPI pen prediction endpoints.
     *
     * Critical fix:
     * The previous implementation referenced non-existent relations / attributes
     * such as `feeder`, `feedingSchedule`, `age_days`, and `current_stage`.
     * This transformer only uses the current schema and optional caller overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function makePenPredictionPayload(Hogpens $pen, array $overrides = []): array
    {
        $hogs = $pen->hogs;

        $payload = [
            'pig_age_days' => $this->resolvePigAgeDays($hogs, $overrides),
            'avg_weight_kg' => $this->resolveAverageWeight($hogs, $overrides),
            'pen_capacity' => $this->resolvePenCapacity($pen, $overrides),
            'feeding_times' => $this->resolveFeedingTimes($pen, $overrides),
            'num_pens' => $this->resolveNumPens($pen, $overrides),
            'current_feed_kg' => $this->resolveCurrentFeedKg($pen, $overrides),
        ];

        $optionalPayload = [
            'feed_type' => $this->resolveFeedType($pen, $overrides),
            'growth_stage' => $this->resolveOptionalString($overrides, 'growth_stage'),
            'device_code' => $this->resolveOptionalString($overrides, 'device_code'),
        ];

        return array_filter(
            array_merge($payload, $optionalPayload),
            static fn (mixed $value): bool => $value !== null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function makeHogHealthPayload(Hogs $hog): array
    {
        $payload = [
            'hog_id' => (int) $hog->id,
            'weight' => (float) $hog->weight_current,
            'age' => (int) $hog->current_age,
            'pen_id' => (int) $hog->hog_pen_id,
        ];

        $latestHealthStatus = $hog->hogDailyRecords()
            ->orderByDesc('recorded_date')
            ->value('health_status');

        if ($latestHealthStatus !== null) {
            $payload['health_status'] = $latestHealthStatus;
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Hogs>  $hogs
     */
    private function resolvePigAgeDays(Collection $hogs, array $overrides): int
    {
        if (array_key_exists('pig_age_days', $overrides) && $overrides['pig_age_days'] !== null) {
            return (int) $overrides['pig_age_days'];
        }

        if ($hogs->isEmpty()) {
            throw new DomainException('Unable to derive pig_age_days because the pen has no hog records.');
        }

        return (int) round((float) $hogs->avg('current_age'));
    }

    /**
     * @param  Collection<int, Hogs>  $hogs
     */
    private function resolveAverageWeight(Collection $hogs, array $overrides): float
    {
        if (array_key_exists('avg_weight_kg', $overrides) && $overrides['avg_weight_kg'] !== null) {
            return round((float) $overrides['avg_weight_kg'], 2);
        }

        if ($hogs->isEmpty()) {
            throw new DomainException('Unable to derive avg_weight_kg because the pen has no hog records.');
        }

        return round((float) $hogs->avg('weight_current'), 2);
    }

    private function resolvePenCapacity(Hogpens $pen, array $overrides): int
    {
        if (array_key_exists('pen_capacity', $overrides) && $overrides['pen_capacity'] !== null) {
            return max(1, (int) $overrides['pen_capacity']);
        }

        return max(1, (int) ($pen->capacity ?? 1));
    }

    /**
     * @return array<int, string>
     */
    private function resolveFeedingTimes(Hogpens $pen, array $overrides): array
    {
        if (array_key_exists('feeding_times', $overrides) && is_array($overrides['feeding_times'])) {
            return $this->normalizeFeedingTimes($overrides['feeding_times']);
        }

        $times = $pen->feedingSchedules
            ->flatMap(function (FeedingSchedule $schedule): array {
                if (is_array($schedule->feeding_times) && $schedule->feeding_times !== []) {
                    return $schedule->feeding_times;
                }

                if ($schedule->time === null) {
                    return [];
                }

                return [$schedule->time->format('H:i')];
            })
            ->filter()
            ->values()
            ->all();

        return $this->normalizeFeedingTimes($times);
    }

    private function resolveNumPens(Hogpens $pen, array $overrides): int
    {
        if (array_key_exists('num_pens', $overrides) && $overrides['num_pens'] !== null) {
            return max(1, (int) $overrides['num_pens']);
        }

        $count = $pen->farm?->relationLoaded('hogpens')
            ? $pen->farm->hogpens->count()
            : $pen->farm?->hogpens()->count();

        return max(1, (int) ($count ?? 1));
    }

    private function resolveCurrentFeedKg(Hogpens $pen, array $overrides): float
    {
        if (array_key_exists('current_feed_kg', $overrides) && $overrides['current_feed_kg'] !== null) {
            return round((float) $overrides['current_feed_kg'], 2);
        }

        $latestSchedule = $pen->feedingSchedules
            ->sortByDesc('time')
            ->first();

        return round((float) ($latestSchedule?->feed_amount ?? 0), 2);
    }

    private function resolveFeedType(Hogpens $pen, array $overrides): ?string
    {
        if (array_key_exists('feed_type', $overrides)) {
            return $this->resolveOptionalString($overrides, 'feed_type');
        }

        $latestSchedule = $pen->feedingSchedules
            ->sortByDesc('time')
            ->first();

        return $latestSchedule?->feed_type;
    }

    private function resolveOptionalString(array $overrides, string $key): ?string
    {
        $value = $overrides[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<int, mixed>  $times
     * @return array<int, string>
     */
    private function normalizeFeedingTimes(array $times): array
    {
        $normalized = array_map(static function (mixed $time): string {
            return date('H:i', strtotime((string) $time));
        }, $times);

        $normalized = array_values(array_unique(array_filter($normalized)));
        sort($normalized);

        return $normalized;
    }
}
