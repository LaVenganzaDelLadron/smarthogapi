<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('sanctum')->plainTextToken;

        return response()->json(
            [
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'token' => $token,
                    'user' => $user,
                ],
            ],
            201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! Auth::attempt($validated)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::user();

        $token = $user->createToken('sanctum')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully.',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $accessToken = $request->user()?->currentAccessToken();

            if ($accessToken && method_exists($accessToken, 'delete')) {
                $accessToken->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
            ], 500);
        }
    }
}
