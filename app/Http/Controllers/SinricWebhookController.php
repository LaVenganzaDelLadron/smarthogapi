<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSinricWebhookRequest;
use App\Services\SinricService;
use Illuminate\Http\JsonResponse;

class SinricWebhookController extends Controller
{
    public function __construct(private SinricService $sinricService) {}

    public function handle(StoreSinricWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->sinricService->handle(
            $validated['deviceId'],
            $validated['action'],
            $validated['value'],
        );

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
