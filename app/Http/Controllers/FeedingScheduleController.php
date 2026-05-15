<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingScheduleRequests;
use App\Models\FeedingSchedule;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class FeedingScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $schedules = FeedingSchedule::with('hogpen.farm')
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedules retrieved successfully',
                'data' => $schedules,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding schedules',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(FeedingScheduleRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);

        try {
            $schedule = FeedingSchedule::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule created successfully',
                'data' => $schedule,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding schedule',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(FeedingSchedule $feedingSchedule): JsonResponse
    {
        abort_unless($feedingSchedule->belongsToUser(auth()->id()), 403);

        try {
            $feedingSchedule->load('hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule retrieved successfully',
                'data' => $feedingSchedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding schedule',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(FeedingScheduleRequests $request, FeedingSchedule $feedingSchedule): JsonResponse
    {
        abort_unless($feedingSchedule->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        try {
            $feedingSchedule->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding schedule updated successfully',
                'data' => $feedingSchedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding schedule',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(FeedingSchedule $feedingSchedule): JsonResponse
    {
        abort_unless($feedingSchedule->belongsToUser(auth()->id()), 403);

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
                'error' => 'Server error',
            ], 500);
        }
    }
}
