<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;

class Sensors extends Model
{
    use UserOwned;

    protected $table = 'sensors';

    protected $fillable = ['hog_pen_id', 'sensor_type', 'device_id', 'status'];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function iotDevice()
    {
        return $this->belongsTo(IotDevices::class, 'device_id');
    }

    public function sensorReadings()
    {
        return $this->hasMany(SensorReadings::class, 'sensor_id');
    }

    //
}
