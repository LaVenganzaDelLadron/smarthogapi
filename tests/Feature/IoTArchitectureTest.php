<?php

namespace Tests\Feature;

use App\Jobs\SendSinricPowerStateJob;
use App\Models\DeviceCommand;
use App\Models\DeviceCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IoTArchitectureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_activity_logs_are_paginated_latest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        DB::table('device_logs')->insert([
            [
                'device_id' => $deviceId,
                'action' => 'olderAction',
                'response' => 'OK',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'device_id' => $deviceId,
                'action' => 'newerAction',
                'response' => 'OK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/v1/activitylogs?per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('activitylogs.0.action', 'newerAction')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_activity_logs_can_be_filtered_by_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);
        $otherDeviceId = $this->createDevice();

        DB::table('device_logs')->insert([
            [
                'device_id' => $deviceId,
                'action' => 'targetAction',
                'response' => 'OK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'device_id' => $otherDeviceId,
                'action' => 'otherAction',
                'response' => 'OK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson("/api/v1/activitylogs/device/{$deviceId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'activitylogs')
            ->assertJsonPath('activitylogs.0.action', 'targetAction');
    }

    public function test_activity_logs_require_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/activitylogs')->assertUnauthorized();
    }

    public function test_user_can_queue_device_action_without_executing_hardware(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'action' => 'dispenseFeed',
            'payload' => [
                'feedType' => 'starter',
                'durationSeconds' => 10,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Command queued for processing.')
            ->assertJsonPath('command.status', 'pending');

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'action' => 'dispenseFeed',
            'status' => 'pending',
        ]);
    }

    public function test_device_action_payload_is_validated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'action' => 'dispenseFeed',
            'payload' => [
                'feedType' => 'starter',
            ],
        ])->assertUnprocessable();
    }

    public function test_legacy_frontend_value_payload_is_normalized_for_device_actions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'clientId' => 'web-dashboard',
            'action' => 'dispenseFeed',
            'value' => [
                'feedType' => 'starter',
                'durationSeconds' => 5,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('command.action', 'dispenseFeed')
            ->assertJsonPath('command.payload.feedType', 'starter')
            ->assertJsonPath('command.payload.durationSeconds', 5);
    }

    public function test_calibrate_feeder_alias_is_accepted_for_legacy_frontend_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'clientId' => 'web-dashboard',
            'action' => 'calibrateFeeder',
            'value' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('command.action', 'calibrateSensor')
            ->assertJsonPath('command.status', 'pending');

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'action' => 'calibrateSensor',
            'status' => 'pending',
        ]);
    }

    public function test_emergency_stop_alias_is_normalized_to_power_off(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'clientId' => 'web-dashboard',
            'action' => 'emergencyStop',
            'payload' => [],
            'value' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('command.action', 'setPowerState')
            ->assertJsonPath('command.payload.state', 'off');

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'action' => 'setPowerState',
            'status' => 'pending',
        ]);
    }

    public function test_sinric_mapped_devices_forward_set_power_state_to_sinric_api(): void
    {
        Http::fake([
            'https://api.sinric.pro/api/v1/auth' => Http::response([
                'success' => true,
                'accessToken' => 'sinric-access-token',
                'expiresIn' => 604800,
            ]),
            'https://api.sinric.pro/api/v1/devices/*/action*' => Http::response([
                'success' => true,
                'message' => 'OK. Your message has been queued for processing.',
            ]),
        ]);

        Cache::flush();
        config()->set('services.sinric.email', 'user@example.com');
        config()->set('services.sinric.password', 'secret-password');
        config()->set('services.sinric.client_id', 'android-app');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user, [
            'external_provider' => 'sinricpro',
            'external_device_id' => 'sinric-switch-1',
        ]);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'action' => 'setPowerState',
            'payload' => [
                'state' => 'on',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('command.action', 'setPowerState');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.sinric.pro/api/v1/auth'
                && $request->hasHeader('Authorization')
                && $request['client_id'] === 'android-app';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/devices/sinric-switch-1/action')
                && $request->hasHeader('Authorization', 'Bearer sinric-access-token')
                && $request['action'] === 'setPowerState'
                && $request['clientId'] === 'android-app'
                && $request['type'] === 'request'
                && $request['value'] === '{"state":"On"}';
        });
    }

    public function test_sinric_mapped_dispense_feed_is_forwarded_as_timed_power_cycle(): void
    {
        Http::fake([
            'https://api.sinric.pro/api/v1/auth' => Http::response([
                'success' => true,
                'accessToken' => 'sinric-access-token',
                'expiresIn' => 604800,
            ]),
            'https://api.sinric.pro/api/v1/devices/*/action*' => Http::response([
                'success' => true,
                'message' => 'OK. Your message has been queued for processing.',
            ]),
        ]);
        Queue::fake();

        Cache::flush();
        config()->set('services.sinric.email', 'user@example.com');
        config()->set('services.sinric.password', 'secret-password');
        config()->set('services.sinric.client_id', 'android-app');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user, [
            'external_provider' => 'sinricpro',
            'external_device_id' => 'sinric-switch-1',
        ]);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'action' => 'dispenseFeed',
            'payload' => [
                'feedType' => 'starter',
                'durationSeconds' => 10,
            ],
        ])->assertCreated();

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/devices/sinric-switch-1/action')
                && $request['action'] === 'setPowerState'
                && $request['value'] === '{"state":"On"}';
        });

        Queue::assertPushed(SendSinricPowerStateJob::class, function (SendSinricPowerStateJob $job) use ($deviceId) {
            $delay = $job->delay;

            return $job->iotDeviceId === $deviceId
                && $job->state === 'off'
                && $delay instanceof \DateTimeInterface
                && $delay->getTimestamp() >= now()->addSeconds(9)->getTimestamp();
        });
    }

    public function test_non_supported_sinric_actions_are_not_forwarded_to_sinric_api(): void
    {
        Http::fake();
        Queue::fake();

        Cache::flush();
        config()->set('services.sinric.email', 'user@example.com');
        config()->set('services.sinric.password', 'secret-password');
        config()->set('services.sinric.client_id', 'android-app');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user, [
            'external_provider' => 'sinricpro',
            'external_device_id' => 'sinric-switch-1',
        ]);

        $this->postJson("/api/v1/iot-devices/{$deviceId}/action", [
            'action' => 'calibrateSensor',
            'payload' => [],
        ])->assertCreated();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_user_can_create_device_credential_and_secret_is_returned_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $deviceId = $this->createDevice($user);

        $response = $this->postJson('/api/v1/device-credentials', [
            'name' => 'ESP32 feeder',
            'iot_device_id' => $deviceId,
            'abilities' => ['commands:poll', 'commands:complete'],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['credential' => ['apiKey'], 'secret']);

        $secret = $response->json('secret');
        $credential = DeviceCredential::query()->firstOrFail();

        $this->assertTrue(Hash::check($secret, $credential->secret));

        $this->getJson('/api/v1/device-credentials')
            ->assertOk()
            ->assertJsonMissing(['secret' => $secret]);
    }

    public function test_device_can_poll_oldest_pending_command(): void
    {
        $deviceId = $this->createDevice();
        [$apiKey, $secret] = $this->createCredential($deviceId, ['commands:poll']);

        DeviceCommand::create([
            'iot_device_id' => $deviceId,
            'action' => 'restartDevice',
            'status' => 'pending',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        DeviceCommand::create([
            'iot_device_id' => $deviceId,
            'action' => 'dispenseFeed',
            'payload' => ['feedType' => 'starter', 'durationSeconds' => 10],
            'status' => 'pending',
        ]);

        $this->withDeviceHeaders($apiKey, $secret)
            ->getJson("/api/v1/iot-devices/{$deviceId}/next-command")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('command.action', 'restartDevice')
            ->assertJsonPath('command.status', 'processing');

        $this->assertDatabaseHas('device_commands', [
            'iot_device_id' => $deviceId,
            'action' => 'restartDevice',
            'status' => 'processing',
        ]);
    }

    public function test_device_can_complete_command_and_write_device_log(): void
    {
        $deviceId = $this->createDevice();
        [$apiKey, $secret] = $this->createCredential($deviceId, ['commands:complete']);

        $command = DeviceCommand::create([
            'iot_device_id' => $deviceId,
            'action' => 'dispenseFeed',
            'payload' => ['feedType' => 'starter', 'durationSeconds' => 10],
            'status' => 'processing',
        ]);

        $this->withDeviceHeaders($apiKey, $secret)
            ->postJson("/api/v1/device-commands/{$command->id}/complete", [
                'status' => 'completed',
                'response' => [
                    'message' => 'Relay executed',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('command.status', 'completed');

        $this->assertDatabaseHas('device_commands', [
            'id' => $command->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('device_logs', [
            'device_id' => $deviceId,
            'action' => 'dispenseFeed',
        ]);
    }

    public function test_device_auth_rejects_missing_credentials(): void
    {
        $deviceId = $this->createDevice();

        $this->getJson("/api/v1/iot-devices/{$deviceId}/next-command")
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDevice(?User $user = null, array $overrides = []): int
    {
        $user ??= User::factory()->create();

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
            'external_provider' => null,
            'external_device_id' => null,
            'external_metadata' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    /**
     * @param  list<string>  $abilities
     * @return array{string, string}
     */
    private function createCredential(int $deviceId, array $abilities): array
    {
        $secret = 'device-secret';
        $apiKey = 'shg_test_'.str()->random(16);

        DeviceCredential::create([
            'user_id' => User::factory()->create()->id,
            'iot_device_id' => $deviceId,
            'name' => 'ESP32',
            'api_key' => $apiKey,
            'secret' => Hash::make($secret),
            'abilities' => $abilities,
        ]);

        return [$apiKey, $secret];
    }

    private function withDeviceHeaders(string $apiKey, string $secret): self
    {
        return $this->withHeaders([
            'X-Device-Api-Key' => $apiKey,
            'X-Device-Secret' => $secret,
        ]);
    }
}
