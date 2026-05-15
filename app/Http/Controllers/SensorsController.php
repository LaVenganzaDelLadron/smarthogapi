<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorsRequests;
use App\Models\Hogpens;
use App\Models\IotDevices;
use App\Models\Sensors;
use Illuminate\Http\JsonResponse;

class SensorsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $sensors = Sensors::with(['hogpen.farm', 'sensorReadings'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'Sensors retrieved successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensors',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(SensorsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);
        abort_unless(IotDevices::query()
            ->where('id', $validated['device_id'])
            ->ownedByUser(auth()->id())
            ->exists(), 403);

        try {
            $sensor = Sensors::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor created successfully',
                'data' => $sensor,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sensor',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(Sensors $sensors): JsonResponse
    {
        abort_unless($sensors->belongsToUser(auth()->id()), 403);

        try {
            $sensors->load('hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Sensor retrieved successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(SensorsRequests $request, Sensors $sensors): JsonResponse
    {
        abort_unless($sensors->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        if (isset($validated['device_id'])) {
            abort_unless(IotDevices::query()
                ->where('id', $validated['device_id'])
                ->ownedByUser(auth()->id())
                ->exists(), 403);
        }

        try {
            $sensors->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor updated successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sensor',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(Sensors $sensors): JsonResponse
    {
        abort_unless($sensors->belongsToUser(auth()->id()), 403);

        try {
            $sensors->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sensor deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sensor',
                'error' => 'Server error',
            ], 500);
        }
    }
}
