<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SinricAuthController extends Controller
{
    private const ACCESS_TOKEN_EXPIRE_SECONDS = 604800;
    private const REFRESH_TOKEN_EXPIRE_SECONDS = 1209600;

    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required_without:clientId', 'string', 'max:255'],
            'clientId' => ['required_without:client_id', 'string', 'max:255'],
        ]);

        $clientId = $validated['client_id'] ?? $validated['clientId'];

        if ($request->header('x-sinric-api-key')) {
            return $this->loginWithApiKey($request->header('x-sinric-api-key'), $clientId);
        }

        if ($request->header('Authorization')) {
            return $this->loginWithCredentials($request, $clientId);
        }

        return response()->json([
            'success' => false,
            'message' => 'Missing x-sinric-api-key or Authorization header.',
        ], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->proxySinricRequest(
            $request->method(),
            '/api/v1/logout',
            [
                'Authorization' => $request->header('Authorization', ''),
                'x-sinric-api-key' => $request->header('x-sinric-api-key', ''),
            ],
        );
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refreshToken' => ['required', 'string'],
            'accountId' => ['required', 'string'],
        ]);

        return $this->proxySinricRequest(
            match ($request->method()) {
                'POST' => 'POST',
                default => 'GET',
            },
            '/api/v1/refresh_token',
            [],
            [
                'refreshToken' => $validated['refreshToken'],
                'accountId' => $validated['accountId'],
            ],
        );
    }

    public function rejectToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refreshToken' => ['required', 'string'],
            'accountId' => ['required', 'string'],
        ]);

        return $this->proxySinricRequest(
            match ($request->method()) {
                'POST' => 'POST',
                default => 'GET',
            },
            '/api/v1/reject_token',
            [],
            [
                'refreshToken' => $validated['refreshToken'],
                'accountId' => $validated['accountId'],
            ],
        );
    }

    protected function loginWithApiKey(string $apiKey, string $clientId): JsonResponse
    {
        return $this->proxySinricRequest(
            'POST',
            '/api/v1/auth',
            ['x-sinric-api-key' => $apiKey],
            ['client_id' => $clientId],
        );
    }

    protected function loginWithCredentials(Request $request, string $clientId): JsonResponse
    {
        $authorization = $request->header('Authorization');

        if (! str_starts_with($authorization, 'Basic ')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Authorization header.',
            ], 401);
        }

        return $this->proxySinricRequest(
            'POST',
            '/api/v1/auth',
            ['Authorization' => $authorization],
            ['client_id' => $clientId],
        );
    }

    protected function proxySinricRequest(string $method, string $uri, array $headers = [], array $payload = []): JsonResponse
    {
        $url = rtrim(config('services.sinric.url'), '/').$uri;

        $request = Http::withHeaders(array_merge([
            'Accept' => 'application/json',
        ], array_filter($headers, fn ($value) => $value !== null && $value !== '')));

        $response = match ($method) {
            'GET' => $request->asForm()->get($url, $payload),
            'POST' => $request->asForm()->post($url, $payload),
            default => $request->asForm()->post($url, $payload),
        };

        $body = $response->json();

        if ($body === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected response from Sinric.',
            ], $response->status());
        }

        return response()->json($body, $response->status());
    }
}
