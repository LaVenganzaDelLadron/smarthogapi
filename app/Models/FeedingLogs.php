<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingLogs extends Model
{
    protected $table = 'feeding_logs';

    protected $fillable = ['feeder_id', 'pen_id', 'feed_amount_given', 'triggered'];

    public function feeder()
    {
        return $this->belongsTo(Feeders::class, 'feeder_id');
    }

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'pen_id');
    }

    //
}
