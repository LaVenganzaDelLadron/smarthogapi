<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertsRequests;
use App\Models\Alerts;
use Illuminate\Http\JsonResponse;

class AlertsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Alerts::with('farm', 'hogpen')->get());
    }

    public function store(AlertsRequests $request): JsonResponse
    {
        $alert = Alerts::create($request->validated());
        return response()->json($alert, 201);
    }

    public function show(Alerts $alerts)
    {
        $alerts->load('farm');
        return response()->json($alerts);
    }

    public function update(AlertsRequests $request, Alerts $alerts)
    {
        $alerts->update($request->validated());
        return response()->json($alerts);
    }

    public function destroy(Alerts $alerts)
    {
        $alerts->delete();
        return response()->json(null, 204);
    }
}

