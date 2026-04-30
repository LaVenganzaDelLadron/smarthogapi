<?php

namespace App\Http\Controllers;

use App\Http\Requests\FarmsRequests;
use App\Models\Farms;
use Illuminate\Http\JsonResponse;

class FarmsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $farms = Farms::with('hogpens', 'dailyFarmReports', 'alerts')->get();

            return response()->json([
                'success' => true,
                'message' => 'Farms retrieved successfully',
                'data' => $farms,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve farms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FarmsRequests $request): JsonResponse
    {
        try {
            $farm = Farms::create($request->validated());
            $farm->load('hogpens');

            return response()->json([
                'success' => true,
                'message' => 'Farm created successfully',
                'data' => $farm,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create farm',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Farms $farm): JsonResponse
    {
        try {
            $farm->load('hogpens.hogs', 'dailyFarmReports');

            return response()->json([
                'success' => true,
                'message' => 'Farm retrieved successfully',
                'data' => $farm,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve farm',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FarmsRequests $request, Farms $farm): JsonResponse
    {
        try {
            $farm->update($request->validated());
            $farm->load('hogpens');

            return response()->json([
                'success' => true,
                'message' => 'Farm updated successfully',
                'data' => $farm,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update farm',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Farms $farm): JsonResponse
    {
        try {
            $farm->delete();

            return response()->json([
                'success' => true,
                'message' => 'Farm deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete farm',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
