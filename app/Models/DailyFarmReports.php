<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyFarmReports extends Model
{
    protected $table = 'daily_farm_reports';

    protected $fillable = [
        'farm_id',
        'report_date',
        'total_feed_consumed',
        'total_hogs',
        'avg_weight',
        'mortality_count',
        'active_pens',
        'avg_temperature',
        'avg_humidity',
        'alerts_triggered',
        'sick_hogs',
        'avg_weekly_weight_gain',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'datetime',
            'total_feed_consumed' => 'decimal:2',
            'avg_weight' => 'decimal:2',
            'mortality_count' => 'decimal:2',
            'avg_temperature' => 'decimal:2',
            'avg_humidity' => 'decimal:2',
            'avg_weekly_weight_gain' => 'decimal:2',
        ];
    }

    public function farm()
    {
        return $this->belongsTo(Farms::class, 'farm_id');
    }
}
