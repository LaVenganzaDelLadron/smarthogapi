<?php

namespace App\Http\Controllers;

use App\Http\Requests\IotDevicesRequests;
use App\Models\Hogpens;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;

class IotDevicesController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $devices = IotDevices::with(['hogpen.farm', 'deviceLogs'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'IoT devices retrieved successfully',
                'data' => $devices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve IoT devices',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(IotDevicesRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);

        try {
            $device = IotDevices::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'IoT device created successfully',
                'data' => $device,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create IoT device',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(IotDevices $iotDevices): JsonResponse
    {
        abort_unless($iotDevices->belongsToUser(auth()->id()), 403);

        try {
            $iotDevices->load('hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'IoT device retrieved successfully',
                'data' => $iotDevices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve IoT device',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(IotDevicesRequests $request, IotDevices $iotDevices): JsonResponse
    {
        abort_unless($iotDevices->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        try {
            $iotDevices->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'IoT device updated successfully',
                'data' => $iotDevices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update IoT device',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(IotDevices $iotDevices): JsonResponse
    {
        abort_unless($iotDevices->belongsToUser(auth()->id()), 403);

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
                'error' => 'Server error',
            ], 500);
        }
    }
}
