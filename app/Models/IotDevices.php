<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotDevices extends Model
{
    protected $table = 'iot_devices';

    protected $fillable = ['hog_pen_id', 'type', 'api_provider', 'status'];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function deviceLogs()
    {
        return $this->hasMany(DeviceLogs::class, 'device_id');
    }

    //
}
