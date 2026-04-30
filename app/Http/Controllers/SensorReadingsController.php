<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorReadingsRequests;
use App\Models\SensorReadings;
use Illuminate\Http\JsonResponse;

class SensorReadingsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $readings = SensorReadings::with('sensor.hogpen')->get();

            return response()->json([
                'success' => true,
                'message' => 'Sensor readings retrieved successfully',
                'data' => $readings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor readings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(SensorReadingsRequests $request): JsonResponse
    {
        try {
            $reading = SensorReadings::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading created successfully',
                'data' => $reading,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sensor reading',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(SensorReadings $sensorReadings): JsonResponse
    {
        try {
            $sensorReadings->load('sensor');

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading retrieved successfully',
                'data' => $sensorReadings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor reading',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(SensorReadingsRequests $request, SensorReadings $sensorReadings): JsonResponse
    {
        try {
            $sensorReadings->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sensor reading updated successfully',
                'data' => $sensorReadings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sensor reading',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(SensorReadings $sensorReadings): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
