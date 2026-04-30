<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedersRequests;
use App\Models\Feeders;
use Illuminate\Http\JsonResponse;

class FeedersController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $feeders = Feeders::with('hogpen', 'feedingLogs')->get();

            return response()->json([
                'success' => true,
                'message' => 'Feeders retrieved successfully',
                'data' => $feeders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feeders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(FeedersRequests $request): JsonResponse
    {
        try {
            $feeder = Feeders::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeder created successfully',
                'data' => $feeder,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feeder',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Feeders $feeders): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(FeedersRequests $request, Feeders $feeders): JsonResponse
    {
        try {
            $feeders->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feeder updated successfully',
                'data' => $feeders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feeder',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Feeders $feeders): JsonResponse
    {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
