<?php

namespace App\Http\Controllers;

use App\Http\Requests\MLModelsRequests;
use App\Models\MLModels;
use Illuminate\Http\JsonResponse;

class MlModelsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(MLModels::all());
    }

    public function store(MLModelsRequests $request): JsonResponse
    {
        $model = MLModels::create($request->validated());
        return response()->json($model, 201);
    }

    public function show(MLModels $mlModels)
    {
        return response()->json($mlModels);
    }

    public function update(MLModelsRequests $request, MLModels $mlModels)
    {
        $mlModels->update($request->validated());
        return response()->json($mlModels);
    }

    public function destroy(MLModels $mlModels)
    {
        $mlModels->delete();
        return response()->json(null, 204);
    }
}

