<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeederFeedTypeMapping extends Model
{
    protected $table = 'feeder_feed_type_mapping';

    protected $fillable = [
        'feeder_id',
        'feed_type',
        'relay_pin',
        'max_duration_seconds',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function feeder()
    {
        return $this->belongsTo(Feeders::class, 'feeder_id');
    }
}
