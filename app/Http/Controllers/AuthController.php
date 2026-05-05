<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
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
                'message' => 'User registered successfully.',
                'token' => $token,
                'user' => $user,
            ],
            201);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        if (!Auth::attempt($validated)) {
            return new JsonResponse(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        // Laravel token
        $token = $user->createToken('sanctum')->plainTextToken;

        // 🔥 Call Sinric API
        try {
            $sinricResponse = Http::asForm()->withHeaders([
                'x-sinric-api-key' => env('71db0e1d-231c-4a07-8ad4-35f5131ed3ea'),
            ])->post('https://api.sinric.pro/api/v1/auth', [
                'client_id' => 'android-app',
                'username' => $user->email, // or mapped username
                'password' => $request->password, // careful here
            ]);

            $sinricToken = null;

            if ($sinricResponse->successful()) {
                $sinricToken = $sinricResponse->json()['accessToken'] ?? null;
            }

        } catch (\Exception $e) {
            $sinricToken = null; // don't break login if Sinric fails
        }

        return response()->json([
            'message' => 'User logged in successfully.',
            'token' => $token,
            'sinric_token' => $sinricToken, // 👈 include this
            'user' => $user,
        ], 200);
    }
}
