<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertsRequests;
use App\Models\Alerts;
use Illuminate\Http\JsonResponse;

class AlertsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $alerts = Alerts::with('farm', 'hogpen')->get();

            return response()->json([
                'success' => true,
                'message' => 'Alerts retrieved successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(AlertsRequests $request): JsonResponse
    {
        try {
            $alert = Alerts::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Alert created successfully',
                'data' => $alert,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create alert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Alerts $alerts): JsonResponse
    {
        try {
            $alerts->load('farm');

            return response()->json([
                'success' => true,
                'message' => 'Alert retrieved successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(AlertsRequests $request, Alerts $alerts): JsonResponse
    {
        try {
            $alerts->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Alert updated successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update alert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Alerts $alerts): JsonResponse
    {
        try {
            $alerts->delete();

            return response()->json([
                'success' => true,
                'message' => 'Alert deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete alert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
