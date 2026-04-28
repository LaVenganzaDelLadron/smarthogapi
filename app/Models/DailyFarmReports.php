<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyFarmReports extends Model
{
    protected $table = "daily_farm_reports";

    protected $fillable = ['farm_id', 'report_date', 'total_feed_consumed', 'total_hogs', 'avg_weight', 'mortality_count'];

    public function farm()
    {
        return $this->belongsTo(Farms::class, 'farm_id');
    }

    //
}
