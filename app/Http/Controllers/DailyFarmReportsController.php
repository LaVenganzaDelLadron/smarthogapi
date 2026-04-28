<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyFarmReportsRequests;
use App\Models\DailyFarmReports;
use Illuminate\Http\JsonResponse;

class DailyFarmReportsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DailyFarmReports::with('farm')->get());
    }

    public function store(DailyFarmReportsRequests $request): JsonResponse
    {
        $report = DailyFarmReports::create($request->validated());
        return response()->json($report, 201);
    }

    public function show(DailyFarmReports $dailyFarmReports)
    {
        return response()->json($dailyFarmReports->load('farm'));
    }

    public function update(DailyFarmReportsRequests $request, DailyFarmReports $dailyFarmReports)
    {
        $dailyFarmReports->update($request->validated());
        return response()->json($dailyFarmReports);
    }

    public function destroy(DailyFarmReports $dailyFarmReports)
    {
        $dailyFarmReports->delete();
        return response()->json(null, 204);
    }
}

