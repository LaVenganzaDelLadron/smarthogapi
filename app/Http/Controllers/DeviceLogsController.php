<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceLogsRequests;
use App\Models\DeviceLogs;
use Illuminate\Http\JsonResponse;

class DeviceLogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $deviceLogs = DeviceLogs::with('iotDevice')->get();

            return response()->json([
                'success' => true,
                'message' => 'Device logs retrieved successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(DeviceLogsRequests $request): JsonResponse
    {
        try {
            $deviceLog = DeviceLogs::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Device log created successfully',
                'data' => $deviceLog,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create device log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(DeviceLogs $deviceLogs): JsonResponse
    {
        try {
            $deviceLogs->load('iotDevice');

            return response()->json([
                'success' => true,
                'message' => 'Device log retrieved successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(DeviceLogsRequests $request, DeviceLogs $deviceLogs): JsonResponse
    {
        try {
            $deviceLogs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Device log updated successfully',
                'data' => $deviceLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(DeviceLogs $deviceLogs): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
