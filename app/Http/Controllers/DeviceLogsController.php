<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceLogsRequests;
use App\Models\DeviceLogs;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;

class DeviceLogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $deviceLogs = DeviceLogs::with('iotDevice.hogpen.farm')
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(100);

            return response()->json([
                'success' => true,
                'message' => 'Device logs retrieved successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device logs',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(DeviceLogsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(IotDevices::query()
            ->where('id', $validated['device_id'])
            ->ownedByUser(auth()->id())
            ->exists(), 403);

        try {
            $deviceLog = DeviceLogs::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Device log created successfully',
                'data' => $deviceLog,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create device log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(DeviceLogs $deviceLogs): JsonResponse
    {
        abort_unless($deviceLogs->belongsToUser(auth()->id()), 403);

        try {
            $deviceLogs->load('iotDevice.hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Device log retrieved successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(DeviceLogsRequests $request, DeviceLogs $deviceLogs): JsonResponse
    {
        abort_unless($deviceLogs->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['device_id'])) {
            abort_unless(IotDevices::query()
                ->where('id', $validated['device_id'])
                ->ownedByUser(auth()->id())
                ->exists(), 403);
        }

        try {
            $deviceLogs->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Device log updated successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(DeviceLogs $deviceLogs): JsonResponse
    {
        abort_unless($deviceLogs->belongsToUser(auth()->id()), 403);

        try {
            $deviceLogs->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device log deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete device log',
                'error' => 'Server error',
            ], 500);
        }
    }
}
