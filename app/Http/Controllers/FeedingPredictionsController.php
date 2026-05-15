<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingPredictionsRequests;
use App\Models\FeedingPredictions;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class FeedingPredictionsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $predictions = FeedingPredictions::with(['hogPen.farm', 'mlModel'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'Feeding predictions retrieved successfully',
                'data' => $predictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding predictions',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(FeedingPredictionsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);

        try {
            $prediction = FeedingPredictions::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction created successfully',
                'data' => $prediction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding prediction',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(FeedingPredictions $feedingPredictions): JsonResponse
    {
        abort_unless($feedingPredictions->belongsToUser(auth()->id()), 403);

        try {
            $feedingPredictions->load('hogPen.farm', 'mlModel');

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction retrieved successfully',
                'data' => $feedingPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding prediction',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(FeedingPredictionsRequests $request, FeedingPredictions $feedingPredictions): JsonResponse
    {
        abort_unless($feedingPredictions->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        try {
            $feedingPredictions->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction updated successfully',
                'data' => $feedingPredictions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding prediction',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(FeedingPredictions $feedingPredictions): JsonResponse
    {
        abort_unless($feedingPredictions->belongsToUser(auth()->id()), 403);

        try {
            $feedingPredictions->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feeding prediction deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feeding prediction',
                'error' => 'Server error',
            ], 500);
        }
    }
}
