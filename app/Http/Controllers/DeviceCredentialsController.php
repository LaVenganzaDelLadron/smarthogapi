<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceCredentialRequest;
use App\Http\Resources\DeviceCredentialResource;
use App\Models\DeviceCredential;
use App\Models\IotDevices;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceCredentialsController extends Controller
{
    public function index(): JsonResponse
    {
        $credentials = DeviceCredential::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'credentials' => DeviceCredentialResource::collection($credentials)->resolve(),
        ]);
    }

    public function store(StoreDeviceCredentialRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['iot_device_id'])) {
            $iotDevice = IotDevices::findOrFail($validated['iot_device_id']);

            abort_unless($iotDevice->belongsToUser($request->user()->id), 403);
        }

        $secret = Str::random(64);

        $credential = DeviceCredential::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'api_key' => 'shg_'.Str::random(40),
            'secret' => Hash::make($secret),
            'abilities' => $validated['abilities'] ?? ['commands:poll', 'commands:complete', 'logs:write'],
        ]);

        return response()->json([
            'success' => true,
            'credential' => DeviceCredentialResource::make($credential)->resolve(),
            'secret' => $secret,
        ], 201);
    }

    public function destroy(DeviceCredential $deviceCredential): JsonResponse
    {
        abort_if($deviceCredential->user_id !== auth()->id(), 403);

        $deviceCredential->update(['revoked_at' => now()]);

        return response()->json([
            'success' => true,
        ]);
    }
}
