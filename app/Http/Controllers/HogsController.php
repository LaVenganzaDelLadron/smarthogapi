<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogsRequests;
use App\Models\Hogs;
use Illuminate\Http\JsonResponse;

class HogsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $hogs = Hogs::with('hogpen', 'hogDailyRecords')->get();

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
        try {
            $hog = Hogs::create($request->validated());

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
