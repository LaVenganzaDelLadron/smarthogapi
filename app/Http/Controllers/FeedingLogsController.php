<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingLogsRequests;
use App\Models\Feeders;
use App\Models\FeedingLogs;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class FeedingLogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $feedingLogs = FeedingLogs::with(['feeder.hogpen.farm', 'hogpen.farm'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Feeding logs retrieved successfully',
                'data' => $feedingLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding logs',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(FeedingLogsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Feeders::query()
            ->where('id', $validated['feeder_id'])
            ->whereHas('hogpen.farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);
        abort_unless(Hogpens::query()
            ->where('id', $validated['pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);

        try {
            $log = FeedingLogs::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding log created successfully',
                'data' => $log,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(FeedingLogs $feedingLogs): JsonResponse
    {
        abort_unless($feedingLogs->belongsToUser(auth()->id()), 403);

        try {
            $feedingLogs->load('feeder');

            return response()->json([
                'success' => true,
                'message' => 'Feeding log retrieved successfully',
                'data' => $feedingLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(FeedingLogsRequests $request, FeedingLogs $feedingLogs): JsonResponse
    {
        abort_unless($feedingLogs->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['feeder_id'])) {
            abort_unless(Feeders::query()
                ->where('id', $validated['feeder_id'])
                ->whereHas('hogpen.farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        if (isset($validated['pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        try {
            $feedingLogs->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding log updated successfully',
                'data' => $feedingLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding log',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(FeedingLogs $feedingLogs): JsonResponse
    {
        abort_unless($feedingLogs->belongsToUser(auth()->id()), 403);

        try {
            $feedingLogs->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feeding log deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feeding log',
                'error' => 'Server error',
            ], 500);
        }
    }
}
