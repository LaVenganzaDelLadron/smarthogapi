<?php

namespace App\Http\Controllers;

use App\Http\Requests\IotDevicesRequests;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;

class IotDevicesController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $devices = IotDevices::with('hogpen', 'deviceLogs')->get();

            return response()->json([
                'success' => true,
                'message' => 'IoT devices retrieved successfully',
                'data' => $devices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve IoT devices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(IotDevicesRequests $request): JsonResponse
    {
        try {
            $device = IotDevices::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'IoT device created successfully',
                'data' => $device,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create IoT device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(IotDevices $iotDevices): JsonResponse
    {
        try {
            $iotDevices->load('hogpen');

            return response()->json([
                'success' => true,
                'message' => 'IoT device retrieved successfully',
                'data' => $iotDevices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve IoT device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(IotDevicesRequests $request, IotDevices $iotDevices): JsonResponse
    {
        try {
            $iotDevices->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'IoT device updated successfully',
                'data' => $iotDevices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update IoT device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(IotDevices $iotDevices): JsonResponse
    {
        try {
            $iotDevices->delete();

            return response()->json([
                'success' => true,
                'message' => 'IoT device deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete IoT device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
