<?php

namespace App\Http\Controllers;

use App\Http\Requests\MLModelsRequests;
use App\Models\MLModels;
use Illuminate\Http\JsonResponse;

class MlModelsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $models = MLModels::all();

            return response()->json([
                'success' => true,
                'message' => 'ML models retrieved successfully',
                'data' => $models,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ML models',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(MLModelsRequests $request): JsonResponse
    {
        try {
            $model = MLModels::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'ML model created successfully',
                'data' => $model,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ML model',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(MLModels $mlModels): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'ML model retrieved successfully',
                'data' => $mlModels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ML model',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(MLModelsRequests $request, MLModels $mlModels): JsonResponse
    {
        try {
            $mlModels->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'ML model updated successfully',
                'data' => $mlModels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ML model',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(MLModels $mlModels): JsonResponse
    {
        try {
            $mlModels->delete();

            return response()->json([
                'success' => true,
                'message' => 'ML model deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ML model',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
