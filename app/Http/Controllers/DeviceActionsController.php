<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceActionRequest;
use App\Http\Resources\DeviceCommandResource;
use App\Jobs\SendSinricPowerStateJob;
use App\Models\IotDevices;
use App\Services\DeviceCommandService;
use App\Services\SinricApiService;
use Illuminate\Http\JsonResponse;

class DeviceActionsController extends Controller
{
    public function __construct(
        private DeviceCommandService $deviceCommandService,
        private SinricApiService $sinricApiService,
    ) {}

    public function store(StoreDeviceActionRequest $request, IotDevices $iotDevice): JsonResponse
    {
        abort_unless($iotDevice->belongsToUser($request->user()->id), 403);

        $validated = $request->validated();
        $command = $this->deviceCommandService->queue($iotDevice, $validated);
        $this->forwardToSinricIfSupported($iotDevice, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Command queued for processing.',
            'command' => DeviceCommandResource::make($command)->resolve(),
        ], 201);
    }

    /**
     * @param  array{action:string,payload?:array<string, mixed>|null}  $validated
     */
    private function forwardToSinricIfSupported(IotDevices $iotDevice, array $validated): void
    {
        if ($iotDevice->external_provider !== 'sinricpro') {
            return;
        }

        if ($validated['action'] !== 'setPowerState') {
            if ($validated['action'] !== 'dispenseFeed') {
                return;
            }

            $durationSeconds = (int) ($validated['payload']['durationSeconds'] ?? 0);

            if ($durationSeconds < 1) {
                return;
            }

            $this->sinricApiService->sendPowerState($iotDevice, ['state' => 'on']);

            SendSinricPowerStateJob::dispatch($iotDevice->id, 'off')
                ->delay(now()->addSeconds($durationSeconds));

            return;
        }

        $this->sinricApiService->sendPowerState($iotDevice, $validated['payload'] ?? []);
    }
}
