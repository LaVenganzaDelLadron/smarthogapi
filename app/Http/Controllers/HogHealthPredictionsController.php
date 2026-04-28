<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogHealthPredictionsRequests;
use App\Models\HogHealthPredictions;
use Illuminate\Http\JsonResponse;

class HogHealthPredictionsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(HogHealthPredictions::with('hog', 'mlModel')->get());
    }

    public function store(HogHealthPredictionsRequests $request): JsonResponse
    {
        $prediction = HogHealthPredictions::create($request->validated());
        return response()->json($prediction, 201);
    }

    public function show(HogHealthPredictions $hogHealthPredictions)
    {
        return response()->json($hogHealthPredictions->load('hog', 'mlModel'));
    }

    public function update(HogHealthPredictionsRequests $request, HogHealthPredictions $hogHealthPredictions)
    {
        $hogHealthPredictions->update($request->validated());
        return response()->json($hogHealthPredictions);
    }

    public function destroy(HogHealthPredictions $hogHealthPredictions)
    {
        $hogHealthPredictions->delete();
        return response()->json(null, 204);
    }
}

