<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogPensRequests;
use App\Models\Farms;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class HogPensController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $hogpens = Hogpens::with(['farm', 'hogs', 'feeders', 'sensors'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(25);

            return response()->json([
                'success' => true,
                'message' => 'Hog pens retrieved successfully',
                'data' => $hogpens,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog pens',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(HogPensRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Farms::query()
            ->where('id', $validated['farm_id'])
            ->where('user_id', auth()->id())
            ->exists(), 403);

        try {
            $hogpen = Hogpens::create($validated);
            $hogpen->load('farm');

            return response()->json([
                'success' => true,
                'message' => 'Hog pen created successfully',
                'data' => $hogpen,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hog pen',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(Hogpens $hogpen): JsonResponse
    {
        abort_unless($hogpen->belongsToUser(auth()->id()), 403);

        try {
            $hogpen->load('farm', 'hogs.hogDailyRecords');

            return response()->json([
                'success' => true,
                'message' => 'Hog pen retrieved successfully',
                'data' => $hogpen,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog pen',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(HogPensRequests $request, Hogpens $hogpen): JsonResponse
    {
        abort_unless($hogpen->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();

        if (isset($validated['farm_id'])) {
            abort_unless(Farms::query()
                ->where('id', $validated['farm_id'])
                ->where('user_id', auth()->id())
                ->exists(), 403);
        }

        try {
            $hogpen->update($validated);
            $hogpen->load('hogs');

            return response()->json([
                'success' => true,
                'message' => 'Hog pen updated successfully',
                'data' => $hogpen,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hog pen',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(Hogpens $hogpen): JsonResponse
    {
        abort_unless($hogpen->belongsToUser(auth()->id()), 403);

        try {
            $hogpen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hog pen deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete hog pen',
                'error' => 'Server error',
            ], 500);
        }
    }
}
