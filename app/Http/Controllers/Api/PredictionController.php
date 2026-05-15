<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchPredictionRequest;
use App\Http\Requests\PredictionRequest;
use App\Models\Hogpens;
use App\Services\FastAPIIntegration;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    public function __construct(private FastAPIIntegration $fastapi) {}

    /**
     * GET /api/predictions/health
     * Check if FastAPI service is healthy
     */
    public function health(): JsonResponse
    {
        $isHealthy = $this->fastapi->healthCheck();

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'unavailable',
            'service' => 'smart-hog-fastapi-integration',
        ], $isHealthy ? 200 : 503);
    }

    /**
     * POST /api/predictions/feed-recommendation
     * Get feed recommendation for a pen
     *
     * Request:
     * {
     *   "pen_id": 1,
     *   "async": false,
     *   "use_cache": true,
     *   "pig_age_days": 30,          // optional override
     *   "avg_weight_kg": 25.5,       // optional override
     * }
     */
    public function feedRecommendation(PredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePen($validated['pen_id']);
        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictFeedRecommendation(
            $validated['pen_id'],
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'prediction_id' => $result['prediction_id'] ?? null,
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    /**
     * POST /api/predictions/weight-trend
     * Get weight trend prediction for a pen
     *
     * Request:
     * {
     *   "pen_id": 1,
     *   "async": false,
     *   "use_cache": true
     * }
     */
    public function weightTrend(PredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePen($validated['pen_id']);
        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictWeightTrend(
            $validated['pen_id'],
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    /**
     * POST /api/predictions/pen-status
     * Classify pen status
     *
     * Request:
     * {
     *   "pen_id": 1,
     *   "async": false,
     *   "use_cache": true
     * }
     */
    public function penStatus(PredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePen($validated['pen_id']);
        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictPenStatus(
            $validated['pen_id'],
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    /**
     * POST /api/predictions/batch/feed-recommendation
     * Batch predict feed recommendations for multiple pens
     *
     * Request:
     * {
     *   "pen_ids": [1, 2, 3],
     *   "async": false
     * }
     */
    public function batchFeedRecommendation(BatchPredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePens($validated['pen_ids']);

        $result = $this->fastapi->batchPredictFeedRecommendation(
            $validated['pen_ids'],
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'count' => $result['count'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    /**
     * POST /api/predictions/batch/weight-trend
     * Batch predict weight trends for multiple pens
     *
     * Request:
     * {
     *   "pen_ids": [1, 2, 3],
     *   "async": false
     * }
     */
    public function batchWeightTrend(BatchPredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePens($validated['pen_ids']);

        $result = $this->fastapi->batchPredictWeightTrend(
            $validated['pen_ids'],
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'count' => $result['count'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    /**
     * POST /api/predictions/batch/pen-status
     * Batch predict pen status for multiple pens
     *
     * Request:
     * {
     *   "pen_ids": [1, 2, 3],
     *   "async": false
     * }
     */
    public function batchPenStatus(BatchPredictionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authorizePens($validated['pen_ids']);

        $result = $this->fastapi->batchPredictPenStatus(
            $validated['pen_ids'],
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed',
            ], 400);
        }

        $statusCode = $request->boolean('async') ? 202 : 200;

        return response()->json([
            'success' => true,
            'count' => $result['count'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $statusCode);
    }

    private function authorizePen(int $penId): void
    {
        abort_unless(Hogpens::query()
            ->where('id', $penId)
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->exists(), 403);
    }

    /**
     * @param  array<int, int>  $penIds
     */
    private function authorizePens(array $penIds): void
    {
        $ownedCount = Hogpens::query()
            ->whereIn('id', $penIds)
            ->whereHas('farm', fn ($query) => $query->where('user_id', auth()->id()))
            ->count();

        abort_unless($ownedCount === count(array_unique($penIds)), 403);
    }
}
