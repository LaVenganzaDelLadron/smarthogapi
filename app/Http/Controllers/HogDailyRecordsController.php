<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogDailyRecordsRequests;
use App\Models\HogDailyRecords;
use Illuminate\Http\JsonResponse;

class HogDailyRecordsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $records = HogDailyRecords::with('hog.hogpen')->get();

            return response()->json([
                'success' => true,
                'message' => 'Hog daily records retrieved successfully',
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog daily records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(HogDailyRecordsRequests $request): JsonResponse
    {
        try {
            $record = HogDailyRecords::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record created successfully',
                'data' => $record,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create hog daily record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(HogDailyRecords $hogDailyRecords): JsonResponse
    {
        try {
            $hogDailyRecords->load('hog');

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record retrieved successfully',
                'data' => $hogDailyRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve hog daily record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(HogDailyRecordsRequests $request, HogDailyRecords $hogDailyRecords): JsonResponse
    {
        try {
            $hogDailyRecords->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hog daily record updated successfully',
                'data' => $hogDailyRecords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hog daily record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(HogDailyRecords $hogDailyRecords): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
