<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingPredictionsRequests;
use App\Models\FeedingPredictions;
use Illuminate\Http\JsonResponse;

class FeedingPredictionsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedingPredictions::with('hogpen', 'mlModel')->get());
    }

    public function store(FeedingPredictionsRequests $request): JsonResponse
    {
        $prediction = FeedingPredictions::create($request->validated());
        return response()->json($prediction, 201);
    }

    public function show(FeedingPredictions $feedingPredictions)
    {
        return response()->json($feedingPredictions->load('hogpen', 'mlModel'));
    }

    public function update(FeedingPredictionsRequests $request, FeedingPredictions $feedingPredictions)
    {
        $feedingPredictions->update($request->validated());
        return response()->json($feedingPredictions);
    }

    public function destroy(FeedingPredictions $feedingPredictions)
    {
        $feedingPredictions->delete();
        return response()->json(null, 204);
    }
}

