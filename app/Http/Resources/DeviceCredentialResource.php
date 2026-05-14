<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceCredentialResource extends JsonResource
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
            'userId' => $this->user_id,
            'iotDeviceId' => $this->iot_device_id,
            'name' => $this->name,
            'apiKey' => $this->api_key,
            'abilities' => $this->abilities,
            'lastUsedAt' => $this->last_used_at?->toISOString(),
            'revokedAt' => $this->revoked_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
