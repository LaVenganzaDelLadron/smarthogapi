<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceLogs extends Model
{
    protected $table = "device_logs";

    protected $fillable = ['device_id', 'action', 'response'];

    public function iotDevice()
    {
        return $this->belongsTo(IotDevices::class, 'device_id');
    }

    //
}
