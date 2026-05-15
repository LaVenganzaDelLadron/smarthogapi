<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class FeedingQueue extends Model
{
    use UserOwned;

    protected $table = 'feeding_queue';

    protected $fillable = [
        'feeder_id',
        'hog_pen_id',
        'feed_type',
        'scheduled_at',
        'actual_feed_time',
        'status',
        'duration_seconds',
        'amount_dispensed',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'actual_feed_time' => 'datetime',
    ];

    public function feeder()
    {
        return $this->belongsTo(Feeders::class, 'feeder_id');
    }

    public function hogPen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
