<?php

namespace App\Http\Middleware;

use App\Models\DeviceCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class DeviceAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $apiKey = $request->header('X-Device-Api-Key');
        $secret = $request->header('X-Device-Secret');

        if (! $apiKey || ! $secret) {
            return response()->json([
                'success' => false,
                'message' => 'Device credentials not found.',
            ], 401);
        }

        $credential = DeviceCredential::query()
            ->with('iotDevice')
            ->where('api_key', $apiKey)
            ->whereNull('revoked_at')
            ->first();

        if (! $credential || ! Hash::check($secret, $credential->secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device credentials.',
            ], 401);
        }

        foreach ($abilities as $ability) {
            if (! $credential->hasAbility($ability)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device credential is missing the required ability.',
                ], 403);
            }
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('device_credential', $credential);
        $request->attributes->set('iot_device', $credential->iotDevice);

        return $next($request);
    }
}
