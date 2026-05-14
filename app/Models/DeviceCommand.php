<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    protected $fillable = [
        'iot_device_id',
        'action',
        'payload',
        'status',
        'executed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function iotDevice(): BelongsTo
    {
        return $this->belongsTo(IotDevices::class, 'iot_device_id');
    }
}
