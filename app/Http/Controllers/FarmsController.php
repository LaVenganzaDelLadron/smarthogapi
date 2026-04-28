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
        $farms = Farms::with('hogpens', 'dailyFarmReports', 'alerts')->get();
        return response()->json($farms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FarmsRequests $request): JsonResponse
    {
        $farm = Farms::create($request->validated());
        $farm->load('hogpens');
        return response()->json($farm, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Farms $farm): JsonResponse
    {
        $farm->load('hogpens.hogs', 'dailyFarmReports');
        return response()->json($farm);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FarmsRequests $request, Farms $farm): JsonResponse
    {
        $farm->update($request->validated());
        $farm->load('hogpens');
        return response()->json($farm);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Farms $farm): JsonResponse
    {
        $farm->delete();
        return response()->json(null, 204);
    }
}

