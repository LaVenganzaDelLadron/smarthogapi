<?php

namespace App\Services;

use App\Models\IotDevices;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use RuntimeException;

class SinricApiService
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @param  array{state:string}  $payload
     */
    public function sendPowerState(IotDevices $iotDevice, array $payload): Response
    {
        if ($iotDevice->external_provider !== 'sinricpro' || ! $iotDevice->external_device_id) {
            throw new RuntimeException('IoT device is not mapped to a Sinric device.');
        }

        $token = (string) config('services.sinric.token');

        if ($token === '') {
            throw new RuntimeException('Sinric API token is not configured.');
        }

        return $this->http
            ->withToken($token)
            ->timeout((int) config('services.sinric.timeout'))
            ->connectTimeout((int) config('services.sinric.connect_timeout'))
            ->retry(2, 250)
            ->acceptJson()
            ->get($this->buildActionUrl($iotDevice->external_device_id), [
                'clientId' => (string) config('services.sinric.client_id'),
                'type' => 'request',
                'createdAt' => now()->getTimestampMs(),
                'action' => 'setPowerState',
                'value' => json_encode([
                    'state' => $this->normalizePowerState(Arr::get($payload, 'state')),
                ], JSON_THROW_ON_ERROR),
            ])
            ->throw();
    }

    private function buildActionUrl(string $deviceId): string
    {
        return rtrim((string) config('services.sinric.base_url'), '/')."/devices/{$deviceId}/action";
    }

    private function normalizePowerState(?string $state): string
    {
        return match ($state) {
            'on' => 'On',
            'off' => 'Off',
            default => throw new RuntimeException('Unsupported SmartHog power state for Sinric forwarding.'),
        };
    }
}
