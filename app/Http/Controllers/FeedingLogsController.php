<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingLogsRequests;
use App\Models\FeedingLogs;
use Illuminate\Http\JsonResponse;

class FeedingLogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $feedingLogs = FeedingLogs::with('feeder.hogpen')->get();

            return response()->json([
                'success' => true,
                'message' => 'Feeding logs retrieved successfully',
                'data' => $feedingLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeding logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(FeedingLogsRequests $request): JsonResponse
    {
        try {
            $log = FeedingLogs::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding log created successfully',
                'data' => $log,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeding log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(FeedingLogs $feedingLogs): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(FeedingLogsRequests $request, FeedingLogs $feedingLogs): JsonResponse
    {
        try {
            $feedingLogs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeding log updated successfully',
                'data' => $feedingLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeding log',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(FeedingLogs $feedingLogs): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
