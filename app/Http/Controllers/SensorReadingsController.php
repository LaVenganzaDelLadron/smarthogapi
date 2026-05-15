<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorReadingsRequests;
use App\Models\SensorReadings;
use App\Models\Sensors;
use Illuminate\Http\JsonResponse;

class SensorReadingsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $readings = SensorReadings::with('sensor.hogpen.farm')
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(100);

            return response()->json([
                'success' => true,
                'message' => 'Sensor readings retrieved successfully',
                'data' => $readings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor readings',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(SensorReadingsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Sensors::query()
            ->where('id', $validated['sensor_id'])
            ->ownedByUser(auth()->id())
            ->exists(), 403);

        try {
            $reading = SensorReadings::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading created successfully',
                'data' => $reading,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sensor reading',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(SensorReadings $sensorReadings): JsonResponse
    {
        abort_unless($sensorReadings->belongsToUser(auth()->id()), 403);

        try {
            $sensorReadings->load('sensor.hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading retrieved successfully',
                'data' => $sensorReadings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor reading',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(SensorReadingsRequests $request, SensorReadings $sensorReadings): JsonResponse
    {
        abort_unless($sensorReadings->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['sensor_id'])) {
            abort_unless(Sensors::query()
                ->where('id', $validated['sensor_id'])
                ->ownedByUser(auth()->id())
                ->exists(), 403);
        }

        try {
            $sensorReadings->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading updated successfully',
                'data' => $sensorReadings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sensor reading',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(SensorReadings $sensorReadings): JsonResponse
    {
        abort_unless($sensorReadings->belongsToUser(auth()->id()), 403);

        try {
            $sensorReadings->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sensor reading',
                'error' => 'Server error',
            ], 500);
        }
    }
}
