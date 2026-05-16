<?php

namespace App\Jobs;

use App\Models\IotDevices;
use App\Services\SinricApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSinricPowerStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $iotDeviceId,
        public string $state,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SinricApiService $sinricApiService): void
    {
        $iotDevice = IotDevices::query()->find($this->iotDeviceId);

        if (! $iotDevice) {
            return;
        }

        $sinricApiService->sendPowerState($iotDevice, [
            'state' => $this->state,
        ]);
    }
}
