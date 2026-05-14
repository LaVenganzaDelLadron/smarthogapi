<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDeviceCommandRequest;
use App\Http\Resources\DeviceCommandResource;
use App\Models\DeviceCommand;
use App\Models\IotDevices;
use App\Services\DeviceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceCommandController extends Controller
{
    public function __construct(private DeviceCommandService $deviceCommandService) {}

    public function next(Request $request, IotDevices $iotDevice): JsonResponse
    {
        $this->ensureCredentialCanAccessDevice($request, $iotDevice);

        $command = $this->deviceCommandService->nextForDevice($iotDevice);

        return response()->json([
            'success' => true,
            'command' => $command ? DeviceCommandResource::make($command)->resolve() : null,
        ]);
    }

    public function complete(CompleteDeviceCommandRequest $request, DeviceCommand $deviceCommand): JsonResponse
    {
        $this->ensureCredentialCanAccessCommand($request, $deviceCommand);

        $command = $this->deviceCommandService->complete($deviceCommand, $request->validated());

        return response()->json([
            'success' => true,
            'command' => DeviceCommandResource::make($command)->resolve(),
        ]);
    }

    private function ensureCredentialCanAccessCommand(Request $request, DeviceCommand $deviceCommand): void
    {
        $iotDevice = $request->attributes->get('iot_device');

        abort_if($iotDevice instanceof IotDevices && $deviceCommand->iot_device_id !== $iotDevice->id, 403);
    }

    private function ensureCredentialCanAccessDevice(Request $request, IotDevices $iotDevice): void
    {
        $authenticatedDevice = $request->attributes->get('iot_device');

        abort_if($authenticatedDevice instanceof IotDevices && $authenticatedDevice->id !== $iotDevice->id, 403);
    }
}
