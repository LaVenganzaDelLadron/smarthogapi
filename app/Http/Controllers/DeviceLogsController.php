<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceLogsRequests;
use App\Models\DeviceLogs;
use Illuminate\Http\JsonResponse;

class DeviceLogsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DeviceLogs::with('iotDevice')->get());
    }

    public function store(DeviceLogsRequests $request): JsonResponse
    {
        $deviceLog = DeviceLogs::create($request->validated());
        return response()->json($deviceLog, 201);
    }

    public function show(DeviceLogs $deviceLogs)
    {
        return response()->json($deviceLogs->load('iotDevice'));
    }

    public function update(DeviceLogsRequests $request, DeviceLogs $deviceLogs)
    {
        $deviceLogs->update($request->validated());
        return response()->json($deviceLogs);
    }

    public function destroy(DeviceLogs $deviceLogs)
    {
        $deviceLogs->delete();
        return response()->json(null, 204);
    }
}

