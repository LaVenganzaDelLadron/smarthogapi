<?php

namespace Tests\Feature;

use App\Models\Farms;
use App\Models\Hogpens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HogPensControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_hog_pen_for_owned_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farms::create([
            'user_id' => $user->id,
            'location' => 'North Barn',
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/hogpens', [
            'farm_id' => $farm->id,
            'name' => 'Small Cage',
            'capacity' => 2,
            'status' => 1,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Small Cage')
            ->assertJsonPath('data.farm.id', $farm->id);

        $this->assertDatabaseHas('hog_pens', [
            'farm_id' => $farm->id,
            'name' => 'Small Cage',
            'capacity' => 2,
            'status' => 1,
        ]);
    }

    public function test_user_cannot_create_hog_pen_for_another_users_farm(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $farm = Farms::create([
            'user_id' => $otherUser->id,
            'location' => 'Other Barn',
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/hogpens', [
            'farm_id' => $farm->id,
            'name' => 'Blocked Cage',
            'capacity' => 2,
            'status' => 1,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('hog_pens', [
            'farm_id' => $farm->id,
            'name' => 'Blocked Cage',
        ]);
        $this->assertSame(0, Hogpens::query()->count());
    }
}
