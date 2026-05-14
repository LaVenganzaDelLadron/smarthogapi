<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceCommandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iotDeviceId' => $this->iot_device_id,
            'action' => $this->action,
            'payload' => $this->payload,
            'status' => $this->status,
            'executedAt' => $this->executed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
