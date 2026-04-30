<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if (! $user) {
            return response()->json(['message' => 'User registration failed.'], 500);
        }

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

        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'User login failed.'], 500);
        }

        $token = $user->createToken('sanctum')->plainTextToken;

        return response()->json([
            'message' => 'User logged in successfully.',
            'token' => $token,
            'user' => $user,
        ], 200);
    }
}
