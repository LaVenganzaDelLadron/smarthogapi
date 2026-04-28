<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingScheduleRequests;
use App\Models\FeedingSchedule;
use Illuminate\Http\JsonResponse;

class FeedingScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedingSchedule::with('hogpen')->get());
    }

    public function store(FeedingScheduleRequests $request): JsonResponse
    {
        $schedule = FeedingSchedule::create($request->validated());
        return response()->json($schedule, 201);
    }

    public function show(FeedingSchedule $feedingSchedule)
    {
        return response()->json($feedingSchedule->load('hogpen'));
    }

    public function update(FeedingScheduleRequests $request, FeedingSchedule $feedingSchedule)
    {
        $feedingSchedule->update($request->validated());
        return response()->json($feedingSchedule);
    }

    public function destroy(FeedingSchedule $feedingSchedule)
    {
        $feedingSchedule->delete();
        return response()->json(null, 204);
    }
}

