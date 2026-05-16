<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SinricWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sinric_webhook_queues_power_command_for_matching_device(): void
    {
        $deviceId = $this->createDevice('sinricpro', 'sinric-switch-1');

        $this->postJson('/api/sinric/webhook', [
            'deviceId' => 'sinric-switch-1',
            'action' => 'setPowerState',
            'value' => [
                'state' => 'On',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'action' => 'setPowerState',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'payload->state' => 'on',
        ]);
    }

    public function test_sinric_webhook_validates_required_payload(): void
    {
        $this->postJson('/api/sinric/webhook', [
            'deviceId' => 'sinric-switch-1',
            'action' => 'setPowerState',
            'value' => [],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('device_commands', 0);
    }

    public function test_sinric_webhook_only_processes_devices_mapped_to_sinric_provider(): void
    {
        $this->createDevice('smarthog', 'sinric-switch-1');

        $this->postJson('/api/sinric/webhook', [
            'deviceId' => 'sinric-switch-1',
            'action' => 'setPowerState',
            'value' => [
                'state' => 'Off',
            ],
        ])->assertNotFound();

        $this->assertDatabaseCount('device_commands', 0);
    }

    private function createDevice(string $externalProvider, string $externalDeviceId): int
    {
        $user = User::factory()->create();

        $farmId = DB::table('farms')->insertGetId([
            'user_id' => $user->id,
            'location' => 'Test Farm',
            'timezone' => 'Asia/Manila',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hogPenId = DB::table('hog_pens')->insertGetId([
            'farm_id' => $farmId,
            'name' => 'Pen A',
            'capacity' => 10,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('iot_devices')->insertGetId([
            'type' => 'feeder',
            'hog_pen_id' => $hogPenId,
            'api_provider' => 'smarthog',
            'external_provider' => $externalProvider,
            'external_device_id' => $externalDeviceId,
            'external_metadata' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
