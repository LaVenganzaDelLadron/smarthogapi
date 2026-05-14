<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SinricAuthController extends Controller
{
    private const ACCESS_TOKEN_EXPIRE_SECONDS = 604800;

    private const REFRESH_TOKEN_EXPIRE_SECONDS = 1209600;

    public function authenticate(Request $request): JsonResponse
    {

        try {
            $validated = $request->validate([
                'client_id' => ['sometimes', 'string', 'max:255'],
                'clientId' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'email'],
                'password' => ['sometimes', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'received_data' => $request->all(),
                'received_headers' => [
                    'authorization' => $request->header('Authorization'),
                    'x-sinric-api-key' => $request->header('x-sinric-api-key'),
                    'content-type' => $request->header('Content-Type'),
                ],
            ], 422);
        }

        $clientId = $validated['client_id'] ?? $validated['clientId'] ?? 'android-app';

        // Check for API key first
        if ($request->header('x-sinric-api-key')) {
            return $this->loginWithApiKey($request->header('x-sinric-api-key'), $clientId);
        }

        // Fallback to server-side Sinric API key from env
        if (config('services.sinric.api_key')) {
            return $this->loginWithApiKey(config('services.sinric.api_key'), $clientId);
        }

        // Check for Basic auth header
        if ($request->header('Authorization') && str_starts_with($request->header('Authorization'), 'Basic ')) {
            return $this->loginWithCredentials($request, $clientId);
        }

        // Check for JSON credentials (email/password in body)
        if (isset($validated['email']) && isset($validated['password'])) {
            return $this->loginWithJsonCredentials($validated['email'], $validated['password'], $clientId);
        }

        return response()->json([
            'success' => false,
            'message' => 'Missing authentication credentials. Provide either x-sinric-api-key header, Basic Authorization header, or email/password in request body.',
            'received_headers' => [
                'authorization' => $request->header('Authorization'),
                'x-sinric-api-key' => $request->header('x-sinric-api-key'),
            ],
            'received_body' => $request->all(),
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

    protected function loginWithJsonCredentials(string $email, string $password, string $clientId): JsonResponse
    {
        // Create Basic auth header from email/password
        $basicAuth = 'Basic '.base64_encode($email.':'.$password);

        return $this->proxySinricRequest(
            'POST',
            '/api/v1/auth',
            [
                'Authorization' => $basicAuth,
                'Content-Type' => 'application/x-www-form-urlencoded', // Sinric expects form data
            ],
            ['client_id' => $clientId],
        );
    }

    protected function proxySinricRequest(string $method, string $uri, array $headers = [], array $payload = []): JsonResponse
    {
        $url = rtrim(config('services.sinric.url'), '/').$uri;

        $request = Http::withHeaders(array_merge([
            'Accept' => 'application/json',
        ], array_filter($headers, fn ($value) => $value !== null && $value !== '')));

        try {
            if ($method === 'POST') {
                // Check if we should send JSON or form data based on Content-Type header
                $contentType = $headers['Content-Type'] ?? '';
                if (str_contains(strtolower($contentType), 'application/json')) {
                    $response = $request->post($url, $payload);
                } else {
                    $response = $request->asForm()->post($url, $payload);
                }
            } elseif ($method === 'GET') {
                $response = $request->asForm()->get($url, $payload);
            } else {
                $response = $request->asForm()->post($url, $payload);
            }

            $body = $response->json();

            if ($body === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unexpected response from Sinric.',
                    'response_body' => $response->body(),
                ], $response->status());
            }

            return response()->json($body, $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Sinric API: '.$e->getMessage(),
            ], 500);
        }
    }
}
