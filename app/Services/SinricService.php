<?php

namespace App\Services;

use App\Models\DeviceCommand;
use App\Models\IotDevices;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class SinricService
{
    public function __construct(private DeviceCommandService $deviceCommandService) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public function handle(string $deviceId, string $action, array $value): DeviceCommand
    {
        $iotDevice = IotDevices::query()
            ->where('external_provider', 'sinricpro')
            ->where('external_device_id', $deviceId)
            ->first();

        if (! $iotDevice) {
            throw (new ModelNotFoundException)->setModel(IotDevices::class, [$deviceId]);
        }

        return $this->deviceCommandService->queue($iotDevice, $this->mapToCommand($action, $value));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{action:string,payload:array<string, mixed>}
     */
    private function mapToCommand(string $action, array $value): array
    {
        return match ($action) {
            'setPowerState' => [
                'action' => 'setPowerState',
                'payload' => [
                    'state' => $this->normalizePowerState($value),
                ],
            ],
            default => throw ValidationException::withMessages([
                'action' => ['Unsupported Sinric action.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function normalizePowerState(array $value): string
    {
        return match ($value['state'] ?? null) {
            'On' => 'on',
            'Off' => 'off',
            default => throw ValidationException::withMessages([
                'value.state' => ['Unsupported Sinric power state.'],
            ]),
        };
    }
}
