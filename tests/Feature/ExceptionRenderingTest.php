<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ExceptionRenderingTest extends TestCase
{
    public function test_api_404_returns_generic_json(): void
    {
        $response = $this->getJson('/api/missing-route');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found',
            ]);
    }

    public function test_api_500_returns_generic_json_without_debug_details(): void
    {
        Route::get('/api/test-server-error', function (): never {
            throw new RuntimeException('Sensitive internal failure');
        });

        $response = $this->getJson('/api/test-server-error');

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'success' => false,
                'message' => 'Server error',
            ]);
    }
}
