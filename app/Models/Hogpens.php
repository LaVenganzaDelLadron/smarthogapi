<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hogpens extends Model
{

    protected $table = "hog_pens";
    protected $fillable = ['farm_id', 'name', 'capacity', 'status'];

    public function farm()
    {
        return $this->belongsTo(Farms::class, 'farm_id');
    }

    public function hogs()
    {
        return $this->hasMany(Hogs::class, 'hog_pen_id');
    }

    public function feeders()
    {
        return $this->hasMany(Feeders::class, 'hog_pen_id');
    }

    public function feedingSchedules()
    {
        return $this->hasMany(FeedingSchedule::class, 'hog_pen_id');
    }

    public function feedingLogs()
    {
        return $this->hasMany(FeedingLogs::class, 'pen_id');
    }

    public function sensors()
    {
        return $this->hasMany(Sensors::class, 'hog_pen_id');
    }

    public function iotDevices()
    {
        return $this->hasMany(IotDevices::class, 'hog_pen_id');
    }

    public function alerts()
    {
        return $this->hasMany(Alerts::class, 'hog_pen_id');
    }

    //
}
