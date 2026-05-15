<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_request_to_login_route_returns_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://smarthogv2.vercel.app',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-skip-success-notification',
        ])->options('/api/auth/login');

        $response
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://smarthogv2.vercel.app')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-Skip-Success-Notification');
    }

    public function test_login_post_includes_cors_headers(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://smarthogv2.vercel.app',
            'X-Skip-Success-Notification' => '1',
        ])->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'https://smarthogv2.vercel.app')
            ->assertJsonPath('user.email', 'test@example.com');
    }
}
