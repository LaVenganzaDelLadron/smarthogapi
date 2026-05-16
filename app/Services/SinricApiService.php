<?php

namespace App\Services;

use App\Models\IotDevices;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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

        $token = $this->getAccessToken();

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
                    'state' => $this->normalizePowerState($payload['state'] ?? null),
                ], JSON_THROW_ON_ERROR),
            ])
            ->throw();
    }

    private function getAccessToken(): string
    {
        return Cache::remember('sinric.access_token', now()->addDays(6), function (): string {
            $email = (string) config('services.sinric.email');
            $password = (string) config('services.sinric.password');

            if ($email === '' || $password === '') {
                throw new RuntimeException('Sinric email/password are not configured.');
            }

            $response = $this->http
                ->withBasicAuth($email, $password)
                ->asForm()
                ->timeout((int) config('services.sinric.timeout'))
                ->connectTimeout((int) config('services.sinric.connect_timeout'))
                ->post(rtrim((string) config('services.sinric.base_url'), '/').'/auth', [
                    'client_id' => (string) config('services.sinric.client_id'),
                ])
                ->throw()
                ->json();

            $token = $response['accessToken'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Sinric access token was not returned.');
            }

            return $token;
        });
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
