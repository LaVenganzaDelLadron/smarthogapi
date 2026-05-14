<?php

namespace App\Services;

use App\Models\DeviceCommand;
use App\Models\DeviceLogs;
use App\Models\IotDevices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceCommandService
{
    /**
     * Queue commands for hardware workers to poll; API requests never touch relays directly.
     *
     * @param  array{action:string,payload?:array<string, mixed>|null}  $validated
     */
    public function queue(IotDevices $iotDevice, array $validated): DeviceCommand
    {
        return $iotDevice->deviceCommands()->create([
            'action' => $validated['action'],
            'payload' => $validated['payload'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function nextForDevice(IotDevices $iotDevice): ?DeviceCommand
    {
        return DB::transaction(function () use ($iotDevice): ?DeviceCommand {
            $command = $iotDevice->deviceCommands()
                ->where('status', 'pending')
                ->oldest()
                ->lockForUpdate()
                ->first();

            if (! $command) {
                return null;
            }

            $command->update(['status' => 'processing']);

            return $command->refresh();
        });
    }

    /**
     * @param  array{status:string,response?:array<string, mixed>|null,message?:string|null}  $validated
     */
    public function complete(DeviceCommand $deviceCommand, array $validated): DeviceCommand
    {
        return DB::transaction(function () use ($deviceCommand, $validated): DeviceCommand {
            $deviceCommand->update([
                'status' => $validated['status'],
                'executed_at' => now(),
            ]);

            DeviceLogs::create([
                'device_id' => $deviceCommand->iot_device_id,
                'action' => $deviceCommand->action,
                'response' => $this->formatResponse($validated),
            ]);

            return $deviceCommand->refresh();
        });
    }

    /**
     * @param  array{status:string,response?:array<string, mixed>|null,message?:string|null}  $validated
     */
    private function formatResponse(array $validated): string
    {
        $response = $validated['response'] ?? ['message' => $validated['message'] ?? $validated['status']];

        return Str::limit((string) json_encode($response, JSON_THROW_ON_ERROR), 255, '');
    }
}
