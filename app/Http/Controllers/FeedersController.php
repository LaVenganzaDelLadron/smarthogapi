<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedersRequests;
use App\Models\Feeders;
use App\Models\Hogpens;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;

class FeedersController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $feeders = Feeders::with(['hogpen.farm', 'feedingLogs'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'Feeders retrieved successfully',
                'data' => $feeders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeders',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(FeedersRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);
        abort_unless(IotDevices::query()
            ->where('id', $validated['device_id'])
            ->ownedByUser(auth()->id())
            ->exists(), 403);

        try {
            $feeder = Feeders::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeder created successfully',
                'data' => $feeder,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeder',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(Feeders $feeders): JsonResponse
    {
        abort_unless($feeders->belongsToUser(auth()->id()), 403);

        try {
            $feeders->load('hogpen');

            return response()->json([
                'success' => true,
                'message' => 'Feeder retrieved successfully',
                'data' => $feeders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeder',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(FeedersRequests $request, Feeders $feeders): JsonResponse
    {
        abort_unless($feeders->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }

        if (isset($validated['device_id'])) {
            abort_unless(IotDevices::query()
                ->where('id', $validated['device_id'])
                ->ownedByUser(auth()->id())
                ->exists(), 403);
        }

        try {
            $feeders->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Feeder updated successfully',
                'data' => $feeders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeder',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(Feeders $feeders): JsonResponse
    {
        abort_unless($feeders->belongsToUser(auth()->id()), 403);

        try {
            $feeders->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feeder deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feeder',
                'error' => 'Server error',
            ], 500);
        }
    }
}
