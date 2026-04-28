<?php

namespace App\Http\Controllers;

use App\Http\Requests\IotDevicesRequests;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;

class IotDevicesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(IotDevices::with('hogpen', 'deviceLogs')->get());
    }

    public function store(IotDevicesRequests $request): JsonResponse
    {
        $device = IotDevices::create($request->validated());
        return response()->json($device, 201);
    }

    public function show(IotDevices $iotDevices)
    {
        return response()->json($iotDevices->load('hogpen'));
    }

    public function update(IotDevicesRequests $request, IotDevices $iotDevices)
    {
        $iotDevices->update($request->validated());
        return response()->json($iotDevices);
    }

    public function destroy(IotDevices $iotDevices)
    {
        $iotDevices->delete();
        return response()->json(null, 204);
    }
}

