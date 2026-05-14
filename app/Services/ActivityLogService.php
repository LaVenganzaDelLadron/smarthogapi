<?php

namespace App\Services;

use App\Models\DeviceLogs;
use App\Models\IotDevices;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogService
{
    public function latest(int $perPage = 15): LengthAwarePaginator
    {
        return DeviceLogs::query()
            ->with('iotDevice')
            ->latest()
            ->paginate($perPage);
    }

    public function latestForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return DeviceLogs::query()
            ->with('iotDevice')
            ->whereHas('iotDevice.hogpen.farm', fn ($query) => $query->where('user_id', $userId))
            ->latest()
            ->paginate($perPage);
    }

    public function latestForDevice(IotDevices $iotDevice, int $perPage = 15): LengthAwarePaginator
    {
        return $iotDevice->deviceLogs()
            ->with('iotDevice')
            ->latest()
            ->paginate($perPage);
    }
}
