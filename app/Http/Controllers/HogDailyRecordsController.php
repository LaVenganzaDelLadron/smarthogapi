<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogDailyRecordsRequests;
use App\Models\HogDailyRecords;
use Illuminate\Http\JsonResponse;

class HogDailyRecordsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(HogDailyRecords::with('hog.hogpen')->get());
    }

    public function store(HogDailyRecordsRequests $request): JsonResponse
    {
        $record = HogDailyRecords::create($request->validated());
        return response()->json($record, 201);
    }

    public function show(HogDailyRecords $hogDailyRecords)
    {
        return response()->json($hogDailyRecords->load('hog'));
    }

    public function update(HogDailyRecordsRequests $request, HogDailyRecords $hogDailyRecords)
    {
        $hogDailyRecords->update($request->validated());
        return response()->json($hogDailyRecords);
    }

    public function destroy(HogDailyRecords $hogDailyRecords)
    {
        $hogDailyRecords->delete();
        return response()->json(null, 204);
    }
}

