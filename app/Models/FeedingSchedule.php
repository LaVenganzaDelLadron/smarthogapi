<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingSchedule extends Model
{
    protected $table = 'feeding_schedule';

    protected $fillable = ['hog_pen_id', 'mode', 'time', 'feed_amount', 'feed_type', 'feeding_times', 'daily_feeding_count'];

    /**
     * Cast attributes to native types
     */
    protected $casts = [
        'feeding_times' => 'array', // Cast JSON to array
        'time' => 'datetime',
        'feed_amount' => 'decimal:2',
    ];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    /**
     * Get the count of daily feeding times
     */
    public function getFeedingTimesCount(): int
    {
        return count($this->feeding_times ?? []);
    }

    /**
     * Check if a specific time is in the feeding schedule
     */
    public function hasTime(string $timeString): bool
    {
        return in_array($timeString, $this->feeding_times ?? []);
    }

    /**
     * Get feeding times sorted in ascending order
     */
    public function getSortedFeedingTimes(): array
    {
        $times = $this->feeding_times ?? [];
        sort($times);

        return $times;
    }

    /**
     * Set feeding times from array and auto-update daily count
     */
    public function setFeedingTimes(array $times): void
    {
        $this->feeding_times = $times;
        $this->daily_feeding_count = count($times);
    }
}
