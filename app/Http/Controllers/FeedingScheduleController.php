<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingScheduleRequests;
use App\Models\FeedingSchedule;
use Illuminate\Http\JsonResponse;

class FeedingScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $schedules = FeedingSchedule::with('hogpen')->get();

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedules retrieved successfully',
                'data' => $schedules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding schedules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(FeedingScheduleRequests $request): JsonResponse
    {
        try {
            $schedule = FeedingSchedule::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule created successfully',
                'data' => $schedule,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(FeedingSchedule $feedingSchedule): JsonResponse
    {
        try {
            $feedingSchedule->load('hogpen');

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule retrieved successfully',
                'data' => $feedingSchedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(FeedingScheduleRequests $request, FeedingSchedule $feedingSchedule): JsonResponse
    {
        try {
            $feedingSchedule->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule updated successfully',
                'data' => $feedingSchedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(FeedingSchedule $feedingSchedule): JsonResponse
    {
        try {
            $feedingSchedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feeding schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
