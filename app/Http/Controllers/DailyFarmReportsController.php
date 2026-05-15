<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyFarmReportsRequests;
use App\Models\DailyFarmReports;
use App\Models\Farms;
use Illuminate\Http\JsonResponse;

class DailyFarmReportsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $reports = DailyFarmReports::with('farm')
                ->ownedByUser(auth()->id())
                ->latest('report_date')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Daily farm reports retrieved successfully',
                'data' => $reports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily farm reports',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(DailyFarmReportsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Farms::query()
            ->where('id', $validated['farm_id'])
            ->where('user_id', auth()->id())
            ->exists(), 403);

        try {
            $report = DailyFarmReports::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report created successfully',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create daily farm report',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(DailyFarmReports $dailyFarmReports): JsonResponse
    {
        abort_unless($dailyFarmReports->belongsToUser(auth()->id()), 403);

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
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(DailyFarmReportsRequests $request, DailyFarmReports $dailyFarmReports): JsonResponse
    {
        abort_unless($dailyFarmReports->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['farm_id'])) {
            abort_unless(Farms::query()
                ->where('id', $validated['farm_id'])
                ->where('user_id', auth()->id())
                ->exists(), 403);
        }

        try {
            $dailyFarmReports->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Daily farm report updated successfully',
                'data' => $dailyFarmReports,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update daily farm report',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(DailyFarmReports $dailyFarmReports): JsonResponse
    {
        abort_unless($dailyFarmReports->belongsToUser(auth()->id()), 403);

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
                'error' => 'Server error',
            ], 500);
        }
    }
}
