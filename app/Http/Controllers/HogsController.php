<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogsRequests;
use App\Models\Hogs;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class HogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $hogs = Hogs::with('hogpen', 'hogDailyRecords')
                ->ownedByUser(auth()->id())
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Hogs retrieved successfully',
                'data' => $hogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hogs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(HogsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(Hogpens::query()
            ->where('id', $validated['hog_pen_id'])
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);

        try {
            $hog = Hogs::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hog created successfully',
                'data' => $hog,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Hogs $hogs): JsonResponse
    {
        abort_unless($hogs->belongsToUser(auth()->id()), 403);

        try {
            $hogs->load('hogpen');

            return response()->json([
                'success' => true,
                'message' => 'Hog retrieved successfully',
                'data' => $hogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(HogsRequests $request, Hogs $hogs): JsonResponse
    {
        abort_unless($hogs->belongsToUser(auth()->id()), 403);

        try {
            $hogs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hog updated successfully',
                'data' => $hogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Hogs $hogs): JsonResponse
    {
        abort_unless($hogs->belongsToUser(auth()->id()), 403);

        try {
            $hogs->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hog deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete hog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
