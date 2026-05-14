<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceActionRequest;
use App\Http\Resources\DeviceCommandResource;
use App\Models\IotDevices;
use App\Services\DeviceCommandService;
use Illuminate\Http\JsonResponse;

class DeviceActionsController extends Controller
{
    public function __construct(private DeviceCommandService $deviceCommandService) {}

    public function store(StoreDeviceActionRequest $request, IotDevices $iotDevice): JsonResponse
    {
        abort_unless($iotDevice->belongsToUser($request->user()->id), 403);

        $command = $this->deviceCommandService->queue($iotDevice, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Command queued for processing.',
            'command' => DeviceCommandResource::make($command)->resolve(),
        ], 201);
    }
}
