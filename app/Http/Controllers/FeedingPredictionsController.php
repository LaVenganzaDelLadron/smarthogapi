<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingPredictionsRequests;
use App\Models\FeedingPredictions;
use Illuminate\Http\JsonResponse;

class FeedingPredictionsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $predictions = FeedingPredictions::with('hogpen', 'mlModel')->get();

            return response()->json([
                'success' => true,
                'message' => 'Feeding predictions retrieved successfully',
                'data' => $predictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding predictions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(FeedingPredictionsRequests $request): JsonResponse
    {
        try {
            $prediction = FeedingPredictions::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction created successfully',
                'data' => $prediction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(FeedingPredictions $feedingPredictions): JsonResponse
    {
        try {
            $feedingPredictions->load('hogpen', 'mlModel');

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction retrieved successfully',
                'data' => $feedingPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(FeedingPredictionsRequests $request, FeedingPredictions $feedingPredictions): JsonResponse
    {
        try {
            $feedingPredictions->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction updated successfully',
                'data' => $feedingPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(FeedingPredictions $feedingPredictions): JsonResponse
    {
        try {
            $feedingPredictions->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feeding prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
