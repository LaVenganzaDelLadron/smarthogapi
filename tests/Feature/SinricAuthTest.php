<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SinricAuthTest extends TestCase
{
    public function test_uses_env_sinric_api_key_when_no_header_provided(): void
    {
        Config::set('services.sinric.api_key', 'env-key');

        Http::fake([
            'https://api.sinric.pro/api/v1/auth' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson('/api/v1/auth', ['client_id' => 'android-app']);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sinric.pro/api/v1/auth'
                && $request->hasHeader('x-sinric-api-key', 'env-key');
        });
    }
}
