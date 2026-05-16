<?php

namespace Tests\Feature;

use App\Models\Farms;
use App\Models\FeedingSchedule;
use App\Models\Hogpens;
use App\Models\Hogs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PredictionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();
        Queue::fake();
        config()->set('services.fastapi.url', 'https://ml-service-api.vercel.app');
    }

    public function test_health_endpoint_returns_ok_when_fastapi_is_healthy(): void
    {
        Http::fake([
            'https://ml-service-api.vercel.app/health' => Http::response([
                'status' => 'ok',
                'service' => 'smart-hog-api',
            ], 200),
        ]);

        $this->getJson('/api/v1/predictions/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'smart-hog-fastapi-integration',
            ]);
    }

    public function test_health_endpoint_returns_service_unavailable_when_fastapi_fails(): void
    {
        Http::fake([
            'https://ml-service-api.vercel.app/health' => Http::response(['status' => 'down'], 500),
        ]);

        $this->getJson('/api/v1/predictions/health')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'service' => 'smart-hog-fastapi-integration',
            ]);
    }

    public function test_feed_recommendation_returns_normalized_prediction_data(): void
    {
        $pen = $this->createOwnedPenWithHogs();

        Http::fake([
            'https://ml-service-api.vercel.app/predict/feed-recommendation' => Http::response([
                'model_used' => 'feed-v2',
                'confidence_level' => 'high',
                'feed_recommendation' => [
                    'recommended_feed_per_pig_per_day' => 2.75,
                    'confidence_score' => 0.91,
                ],
                'feed_totals' => [
                    'daily_total_kg' => 22,
                ],
                'warnings' => [],
                'alerts' => [],
                'suggestions' => ['Keep current schedule'],
            ], 200),
        ]);

        Sanctum::actingAs($pen->farm->user);

        $this->postJson('/api/v1/predictions/feed-recommendation', [
            'pen_id' => $pen->id,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prediction_type', 'feed_recommendation')
            ->assertJsonPath('data.predicted_feed_amount', 2.75)
            ->assertJsonPath('data.confidence_score', 0.91)
            ->assertJsonPath('data.feed_totals.daily_total_kg', 22);
    }

    public function test_prediction_timeout_returns_gateway_timeout(): void
    {
        $pen = $this->createOwnedPenWithHogs();

        Http::fake([
            'https://ml-service-api.vercel.app/predict/feed-recommendation' => Http::failedConnection(),
        ]);

        Sanctum::actingAs($pen->farm->user);

        $this->postJson('/api/v1/predictions/feed-recommendation', [
            'pen_id' => $pen->id,
        ])
            ->assertStatus(504)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Feed recommendation failed');
    }

    public function test_prediction_upstream_server_error_returns_bad_gateway(): void
    {
        $pen = $this->createOwnedPenWithHogs();

        Http::fake([
            'https://ml-service-api.vercel.app/predict/feed-recommendation' => Http::response([
                'detail' => 'upstream error',
            ], 500),
        ]);

        Sanctum::actingAs($pen->farm->user);

        $this->postJson('/api/v1/predictions/feed-recommendation', [
            'pen_id' => $pen->id,
        ])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Feed recommendation failed');
    }

    public function test_user_cannot_predict_for_another_users_pen(): void
    {
        $user = User::factory()->create();
        $otherPen = $this->createOwnedPenWithHogs();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/predictions/feed-recommendation', [
            'pen_id' => $otherPen->id,
        ])->assertForbidden();
    }

    public function test_prediction_request_validates_pen_id_and_feeding_times(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/predictions/feed-recommendation', [
            'feeding_times' => ['bad-time'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed');

        $errors = $response->json('data.errors');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('pen_id', $errors);
        $this->assertArrayHasKey('feeding_times.0', $errors);
    }

    private function createOwnedPenWithHogs(): Hogpens
    {
        $user = User::factory()->create();

        $farm = new Farms([
            'location' => 'Test Farm',
            'timezone' => 'Asia/Manila',
        ]);
        $farm->user_id = $user->id;
        $farm->save();

        $pen = Hogpens::query()->create([
            'farm_id' => $farm->id,
            'name' => 'Pen A',
            'capacity' => 8,
            'status' => 1,
        ]);

        Hogs::query()->create([
            'hog_pen_id' => $pen->id,
            'ear_tag_id' => 'A-001',
            'breed' => 'Large White',
            'gender' => 'female',
            'current_age' => 70,
            'weight_current' => 31.4,
        ]);

        Hogs::query()->create([
            'hog_pen_id' => $pen->id,
            'ear_tag_id' => 'A-002',
            'breed' => 'Landrace',
            'gender' => 'male',
            'current_age' => 72,
            'weight_current' => 33.2,
        ]);

        FeedingSchedule::query()->create([
            'hog_pen_id' => $pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0),
            'feed_amount' => 5.5,
            'feed_type' => 'grower',
            'feeding_times' => ['06:00', '14:00'],
            'daily_feeding_count' => 2,
        ]);

        return $pen->fresh(['farm.user']);
    }
}
