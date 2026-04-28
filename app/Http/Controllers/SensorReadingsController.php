<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorReadingsRequests;
use App\Models\SensorReadings;
use Illuminate\Http\JsonResponse;

class SensorReadingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SensorReadings::with('sensor.hogpen')->get());
    }

    public function store(SensorReadingsRequests $request): JsonResponse
    {
        $reading = SensorReadings::create($request->validated());
        return response()->json($reading, 201);
    }

    public function show(SensorReadings $sensorReadings)
    {
        return response()->json($sensorReadings->load('sensor'));
    }

    public function update(SensorReadingsRequests $request, SensorReadings $sensorReadings)
    {
        $sensorReadings->update($request->validated());
        return response()->json($sensorReadings);
    }

    public function destroy(SensorReadings $sensorReadings)
    {
        $sensorReadings->delete();
        return response()->json(null, 204);
    }
}

