<?php

namespace App\Http\Controllers;

use App\Http\Requests\HogsRequests;
use App\Models\Hogs;
use Illuminate\Http\JsonResponse;

class HogsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Hogs::with('hogpen', 'hogDailyRecords')->get());
    }

    public function store(HogsRequests $request): JsonResponse
    {
        $hog = Hogs::create($request->validated());
        return response()->json($hog, 201);
    }

    public function show(Hogs $hogs)
    {
        return response()->json($hogs->load('hogpen'));
    }

    public function update(HogsRequests $request, Hogs $hogs)
    {
        $hogs->update($request->validated());
        return response()->json($hogs);
    }

    public function destroy(Hogs $hogs)
    {
        $hogs->delete();
        return response()->json(null, 204);
    }
}

