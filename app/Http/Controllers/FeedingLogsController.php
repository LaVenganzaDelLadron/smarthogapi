<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedingLogsRequests;
use App\Models\FeedingLogs;
use Illuminate\Http\JsonResponse;

class FeedingLogsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedingLogs::with('feeder.hogpen')->get());
    }

    public function store(FeedingLogsRequests $request): JsonResponse
    {
        $log = FeedingLogs::create($request->validated());
        return response()->json($log, 201);
    }

    public function show(FeedingLogs $feedingLogs)
    {
        return response()->json($feedingLogs->load('feeder'));
    }

    public function update(FeedingLogsRequests $request, FeedingLogs $feedingLogs)
    {
        $feedingLogs->update($request->validated());
        return response()->json($feedingLogs);
    }

    public function destroy(FeedingLogs $feedingLogs)
    {
        $feedingLogs->delete();
        return response()->json(null, 204);
    }
}

