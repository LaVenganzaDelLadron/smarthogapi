<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedersRequests;
use App\Models\Feeders;
use Illuminate\Http\JsonResponse;

class FeedersController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Feeders::with('hogpen', 'feedingLogs')->get());
    }

    public function store(FeedersRequests $request): JsonResponse
    {
        $feeder = Feeders::create($request->validated());
        return response()->json($feeder, 201);
    }

    public function show(Feeders $feeders)
    {
        return response()->json($feeders->load('hogpen'));
    }

    public function update(FeedersRequests $request, Feeders $feeders)
    {
        $feeders->update($request->validated());
        return response()->json($feeders);
    }

    public function destroy(Feeders $feeders)
    {
        $feeders->delete();
        return response()->json(null, 204);
    }
}

