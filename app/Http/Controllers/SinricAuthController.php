<?php

namespace App\Http\Controllers;

use App\Models\DeviceCredential;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SinricAuthController extends Controller
{
    private const ACCESS_TOKEN_EXPIRE_SECONDS = 604800;
    private const REFRESH_TOKEN_EXPIRE_SECONDS = 1209600;

    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
        ]);

        $clientId = $validated['client_id'];

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
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['success' => true]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refreshToken' => ['required', 'string'],
            'accountId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $refreshToken = RefreshToken::query()
            ->where('user_id', $validated['accountId'])
            ->where('token_hash', hash('sha256', $validated['refreshToken']))
            ->valid()
            ->first();

        if (! $refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        $refreshToken->update(['revoked_at' => now()]);

        return $this->issueTokens($refreshToken->user, $refreshToken->client_id, $refreshToken->deviceCredential);
    }

    public function rejectToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refreshToken' => ['required', 'string'],
            'accountId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $refreshToken = RefreshToken::query()
            ->where('user_id', $validated['accountId'])
            ->where('token_hash', hash('sha256', $validated['refreshToken']))
            ->first();

        if (! $refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token not found.',
            ], 404);
        }

        $refreshToken->update(['revoked_at' => now()]);

        return response()->json(['success' => true]);
    }

    protected function loginWithApiKey(string $apiKey, string $clientId): JsonResponse
    {
        $credential = DeviceCredential::query()
            ->where('api_key', $apiKey)
            ->whereNull('revoked_at')
            ->first();

        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], 403);
        }

        return $this->issueTokens($credential->user, $clientId, $credential);
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

        $encoded = substr($authorization, 6);
        $decoded = base64_decode($encoded);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Basic auth payload.',
            ], 401);
        }

        [$email, $password] = explode(':', $decoded, 2);

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return $this->issueTokens(Auth::user(), $clientId);
    }

    protected function issueTokens(User $user, string $clientId, ?DeviceCredential $credential = null): JsonResponse
    {
        $tokenName = 'sinric:'.$clientId.'@'.$user->id;
        $newAccessToken = $user->createToken($tokenName);
        $accessToken = $newAccessToken->plainTextToken;

        $personalAccessToken = $newAccessToken->accessToken;
        $personalAccessToken->expires_at = now()->addSeconds(self::ACCESS_TOKEN_EXPIRE_SECONDS);
        $personalAccessToken->save();

        $refreshToken = Str::random(80);

        RefreshToken::create([
            'user_id' => $user->id,
            'device_credential_id' => $credential?->id,
            'token_hash' => hash('sha256', $refreshToken),
            'client_id' => $clientId,
            'expires_at' => now()->addSeconds(self::REFRESH_TOKEN_EXPIRE_SECONDS),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OK.',
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn' => self::ACCESS_TOKEN_EXPIRE_SECONDS,
            'subscriptionExpired' => false,
            'account' => $user->makeHidden(['password', 'remember_token'])->toArray(),
        ]);
    }
}
