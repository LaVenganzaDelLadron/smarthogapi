<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingSchedule extends Model
{
    protected $table = 'feeding_schedule';

    protected $fillable = ['hog_pen_id', 'mode', 'time', 'feed_amount', 'feed_type'];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    //
}
