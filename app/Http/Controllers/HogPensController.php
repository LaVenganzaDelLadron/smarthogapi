<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogPensRequests;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class HogPensController extends Controller
{
    public function index(): JsonResponse
    {
        $hogpens = Hogpens::with('farm', 'hogs', 'feeders', 'sensors')->get();
        return response()->json($hogpens);
    }

    public function store(HogPensRequests $request): JsonResponse
    {
        $hogpen = Hogpens::create($request->validated());
        $hogpen->load('farm');
        return response()->json($hogpen, 201);
    }

    public function show(Hogpens $hogpen): JsonResponse
    {
        $hogpen->load('farm', 'hogs.hogDailyRecords');
        return response()->json($hogpen);
    }

    public function update(HogPensRequests $request, Hogpens $hogpen): JsonResponse
    {
        $hogpen->update($request->validated());
        $hogpen->load('hogs');
        return response()->json($hogpen);
    }

    public function destroy(Hogpens $hogpen): JsonResponse
    {
        $hogpen->delete();
        return response()->json(null, 204);
    }
}
