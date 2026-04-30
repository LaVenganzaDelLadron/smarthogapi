<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorsRequests;
use App\Models\Sensors;
use Illuminate\Http\JsonResponse;

class SensorsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $sensors = Sensors::with('hogpen', 'sensorReadings')->get();

            return response()->json([
                'success' => true,
                'message' => 'Sensors retrieved successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensors',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(SensorsRequests $request): JsonResponse
    {
        try {
            $sensor = Sensors::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sensor created successfully',
                'data' => $sensor,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sensor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Sensors $sensors): JsonResponse
    {
        try {
            $sensors->load('hogpen');

            return response()->json([
                'success' => true,
                'message' => 'Sensor retrieved successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sensor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(SensorsRequests $request, Sensors $sensors): JsonResponse
    {
        try {
            $sensors->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sensor updated successfully',
                'data' => $sensors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sensor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Sensors $sensors): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
