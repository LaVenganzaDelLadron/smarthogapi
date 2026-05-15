<?php

namespace Tests\Feature;

use App\Models\Alerts;
use App\Models\Farms;
use App\Models\FeedingSchedule;
use App\Models\Hogpens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_index_only_returns_authenticated_users_farms(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();
        $ownedFarm = $this->createFarmForUser($user, 'Owned Farm');
        $otherFarm = $this->createFarmForUser($otherUser, 'Other Farm');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/farms');

        $response
            ->assertOk()
            ->assertJsonFragment(['id' => $ownedFarm->id])
            ->assertJsonMissing(['id' => $otherFarm->id]);
    }

    public function test_direct_access_to_another_users_farm_is_forbidden(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();
        $otherFarm = $this->createFarmForUser($otherUser, 'Other Farm');

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/farms/{$otherFarm->id}")
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden',
            ]);
    }

    public function test_child_resources_are_scoped_through_farm_ownership(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();
        $ownedPen = $this->createPenForUser($user, 'Owned Pen');
        $otherPen = $this->createPenForUser($otherUser, 'Other Pen');
        $ownedSchedule = FeedingSchedule::query()->create([
            'hog_pen_id' => $ownedPen->id,
            'mode' => 'auto',
            'time' => now(),
            'feed_amount' => 10,
            'feed_type' => 'starter',
        ]);
        $otherSchedule = FeedingSchedule::query()->create([
            'hog_pen_id' => $otherPen->id,
            'mode' => 'auto',
            'time' => now(),
            'feed_amount' => 20,
            'feed_type' => 'grower',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/feeding-schedule')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownedSchedule->id])
            ->assertJsonMissing(['id' => $otherSchedule->id]);

        $this->getJson("/api/v1/feeding-schedule/{$otherSchedule->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_create_alert_for_another_users_pen(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();
        $ownedPen = $this->createPenForUser($user, 'Owned Pen');
        $otherPen = $this->createPenForUser($otherUser, 'Other Pen');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/alerts', [
            'farm_id' => $ownedPen->farm_id,
            'hog_pen_id' => $otherPen->id,
            'type' => 'temperature',
            'message' => 'Cross-tenant alert attempt',
            'severity' => 'high',
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseMissing((new Alerts)->getTable(), [
            'message' => 'Cross-tenant alert attempt',
        ]);
    }

    private function createPenForUser(User $user, string $name): Hogpens
    {
        $farm = new Farms([
            'location' => "{$name} Farm",
            'timezone' => 'Asia/Manila',
        ]);
        $farm->user_id = $user->id;
        $farm->save();

        return Hogpens::query()->create([
            'farm_id' => $farm->id,
            'name' => $name,
            'capacity' => 100,
            'status' => 1,
        ]);
    }

    private function createFarmForUser(User $user, string $location): Farms
    {
        $farm = new Farms([
            'location' => $location,
            'timezone' => 'Asia/Manila',
        ]);
        $farm->user_id = $user->id;
        $farm->save();

        return $farm;
    }
}
