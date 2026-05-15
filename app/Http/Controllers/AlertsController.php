<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertsRequests;
use App\Models\Alerts;
use App\Models\Farms;
use App\Models\Hogpens;
use Illuminate\Http\JsonResponse;

class AlertsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $alerts = Alerts::with(['farm', 'hogpen.farm'])
                ->ownedByUser(auth()->id())
                ->latest()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Alerts retrieved successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function store(AlertsRequests $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizeFarmAndPen($validated);

        try {
            $alert = Alerts::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Alert created successfully',
                'data' => $alert,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create alert',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function show(Alerts $alerts): JsonResponse
    {
        abort_unless($alerts->belongsToUser(auth()->id()), 403);

        try {
            $alerts->load('farm', 'hogpen.farm');

            return response()->json([
                'success' => true,
                'message' => 'Alert retrieved successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alert',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function update(AlertsRequests $request, Alerts $alerts): JsonResponse
    {
        abort_unless($alerts->belongsToUser(auth()->id()), 403);
        $validated = $request->validated();
        $this->authorizeFarmAndPen($validated);

        try {
            $alerts->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Alert updated successfully',
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update alert',
                'error' => 'Server error',
            ], 500);
        }
    }

    public function destroy(Alerts $alerts): JsonResponse
    {
        abort_unless($alerts->belongsToUser(auth()->id()), 403);

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
                'error' => 'Server error',
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function authorizeFarmAndPen(array $validated): void
    {
        if (isset($validated['farm_id'])) {
            abort_unless(Farms::query()
                ->where('id', $validated['farm_id'])
                ->where('user_id', auth()->id())
                ->exists(), 403);
        }

        if (isset($validated['hog_pen_id'])) {
            abort_unless(Hogpens::query()
                ->where('id', $validated['hog_pen_id'])
                ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
                ->when(isset($validated['farm_id']), fn ($query) => $query->where('farm_id', $validated['farm_id']))
                ->exists(), 403);
        }
    }
}
