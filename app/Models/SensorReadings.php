<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorReadings extends Model
{
    protected $table = 'sensor_readings';

    protected $fillable = ['sensor_id', 'value', 'unit'];

    public function sensor()
    {
        return $this->belongsTo(Sensors::class, 'sensor_id');
    }

    //
}
