<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IotDevices extends Model
{
    use UserOwned;

    protected $table = 'iot_devices';

    protected $fillable = ['hog_pen_id', 'type', 'api_provider', 'status'];

    public function hogpen(): BelongsTo
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function deviceLogs(): HasMany
    {
        return $this->hasMany(DeviceLogs::class, 'device_id');
    }

    public function deviceCommands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class, 'iot_device_id');
    }

    public function deviceCredentials(): HasMany
    {
        return $this->hasMany(DeviceCredential::class, 'iot_device_id');
    }
}
