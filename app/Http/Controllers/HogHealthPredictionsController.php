<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogHealthPredictionsRequests;
use App\Models\HogHealthPredictions;
use Illuminate\Http\JsonResponse;

class HogHealthPredictionsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $predictions = HogHealthPredictions::with('hog', 'mlModel')->get();

            return response()->json([
                'success' => true,
                'message' => 'Hog health predictions retrieved successfully',
                'data' => $predictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog health predictions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(HogHealthPredictionsRequests $request): JsonResponse
    {
        try {
            $prediction = HogHealthPredictions::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hog health prediction created successfully',
                'data' => $prediction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hog health prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(HogHealthPredictions $hogHealthPredictions): JsonResponse
    {
        try {
            $hogHealthPredictions->load('hog', 'mlModel');

            return response()->json([
                'success' => true,
                'message' => 'Hog health prediction retrieved successfully',
                'data' => $hogHealthPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog health prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(HogHealthPredictionsRequests $request, HogHealthPredictions $hogHealthPredictions): JsonResponse
    {
        try {
            $hogHealthPredictions->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hog health prediction updated successfully',
                'data' => $hogHealthPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hog health prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(HogHealthPredictions $hogHealthPredictions): JsonResponse
    {
        try {
            $hogHealthPredictions->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hog health prediction deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete hog health prediction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
