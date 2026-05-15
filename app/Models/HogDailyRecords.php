<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class HogDailyRecords extends Model
{
    use UserOwned;

    protected $table = 'hog_daily_records';

    protected $fillable = ['hog_id', 'hog_pen_id', 'weight', 'feed_consumed', 'health_status', 'temperature', 'activity_level', 'notes', 'recorded_date'];

    public function hog()
    {
        return $this->belongsTo(Hogs::class, 'hog_id');
    }

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    //
}
