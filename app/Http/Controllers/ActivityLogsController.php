<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListActivityLogsRequest;
use App\Http\Resources\DeviceLogResource;
use App\Models\IotDevices;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ActivityLogsController extends Controller
{
    public function __construct(private ActivityLogService $activityLogService) {}

    public function index(ListActivityLogsRequest $request): JsonResponse
    {
        $logs = $this->activityLogService->latestForUser($request->user()->id, $this->perPage($request));

        return $this->paginatedResponse($logs);
    }

    public function forDevice(ListActivityLogsRequest $request, IotDevices $iotDevice): JsonResponse
    {
        abort_unless($iotDevice->belongsToUser($request->user()->id), 403);

        $logs = $this->activityLogService->latestForDevice($iotDevice, $this->perPage($request));

        return $this->paginatedResponse($logs);
    }

    private function perPage(ListActivityLogsRequest $request): int
    {
        return (int) ($request->validated('per_page') ?? 15);
    }

    private function paginatedResponse(LengthAwarePaginator $logs): JsonResponse
    {
        return response()->json([
            'success' => true,
            'activitylogs' => DeviceLogResource::collection($logs->getCollection())->resolve(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
