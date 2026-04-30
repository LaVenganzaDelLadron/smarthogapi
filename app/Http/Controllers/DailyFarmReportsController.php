<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyFarmReportsRequests;
use App\Models\DailyFarmReports;
use Illuminate\Http\JsonResponse;

class DailyFarmReportsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $reports = DailyFarmReports::with('farm')->get();

            return response()->json([
                'success' => true,
                'message' => 'Daily farm reports retrieved successfully',
                'data' => $reports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily farm reports',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(DailyFarmReportsRequests $request): JsonResponse
    {
        try {
            $report = DailyFarmReports::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report created successfully',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create daily farm report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(DailyFarmReports $dailyFarmReports): JsonResponse
    {
        try {
            $dailyFarmReports->load('farm');

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report retrieved successfully',
                'data' => $dailyFarmReports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily farm report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(DailyFarmReportsRequests $request, DailyFarmReports $dailyFarmReports): JsonResponse
    {
        try {
            $dailyFarmReports->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report updated successfully',
                'data' => $dailyFarmReports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update daily farm report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(DailyFarmReports $dailyFarmReports): JsonResponse
    {
        try {
            $dailyFarmReports->delete();

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete daily farm report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
