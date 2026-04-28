<?php

namespace App\Http\Controllers;

use App\Http\Requests\SensorsRequests;
use App\Models\Sensors;
use Illuminate\Http\JsonResponse;

class SensorsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Sensors::with('hogpen', 'sensorReadings')->get());
    }

    public function store(SensorsRequests $request): JsonResponse
    {
        $sensor = Sensors::create($request->validated());
        return response()->json($sensor, 201);
    }

    public function show(Sensors $sensors)
    {
        return response()->json($sensors->load('hogpen'));
    }

    public function update(SensorsRequests $request, Sensors $sensors)
    {
        $sensors->update($request->validated());
        return response()->json($sensors);
    }

    public function destroy(Sensors $sensors)
    {
        $sensors->delete();
        return response()->json(null, 204);
    }
}

