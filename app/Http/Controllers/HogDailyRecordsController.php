<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogDailyRecordsRequests;
use App\Models\HogDailyRecords;
use App\Models\Hogpens;
use App\Models\Hogs;
use Illuminate\Http\JsonResponse;

class HogDailyRecordsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $records = HogDailyRecords::with(['hog.hogpen.farm', 'hogpen.farm'])
                ->ownedByUser(auth()->id())
                ->latest('recorded_date')
                ->paginate(100);

            return response()->json([
                'success' => true,
                'message' => 'Hog daily records retrieved successfully',
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog daily records',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(HogDailyRecordsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizeHogRecordInput($validated);

        try {
            $record = HogDailyRecords::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record created successfully',
                'data' => $record,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hog daily record',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(HogDailyRecords $hogDailyRecords): JsonResponse
    {
        abort_unless($hogDailyRecords->belongsToUser(auth()->id()), 403);

        try {
            $hogDailyRecords->load('hog.hogpen.farm', 'hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record retrieved successfully',
                'data' => $hogDailyRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog daily record',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(HogDailyRecordsRequests $request, HogDailyRecords $hogDailyRecords): JsonResponse
    {
        abort_unless($hogDailyRecords->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();
        $this->authorizeHogRecordInput($validated);

        try {
            $hogDailyRecords->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record updated successfully',
                'data' => $hogDailyRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hog daily record',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(HogDailyRecords $hogDailyRecords): JsonResponse
    {
        abort_unless($hogDailyRecords->belongsToUser(auth()->id()), 403);

        try {
            $hogDailyRecords->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete hog daily record',
                'error' => 'Server error',
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function authorizeHogRecordInput(array $validated): void
    {
        if (isset($validated['hog_id'])) {
            abort_unless(Hogs::query()
                ->where('id', $validated['hog_id'])
                ->ownedByUser(auth()->id())
                ->exists(), 403);
        }

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->exists(), 403);
        }
    }
}
