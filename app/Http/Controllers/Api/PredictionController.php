<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FastAPIIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function feedRecommendation(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
            'async' => 'boolean',
            'use_cache' => 'boolean',
        ]);

        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictFeedRecommendation(
            $request->integer('pen_id'),
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
    public function weightTrend(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
            'async' => 'boolean',
            'use_cache' => 'boolean',
        ]);

        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictWeightTrend(
            $request->integer('pen_id'),
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
    public function penStatus(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
            'async' => 'boolean',
            'use_cache' => 'boolean',
        ]);

        $overrides = $request->except(['pen_id', 'async', 'use_cache']);

        $result = $this->fastapi->predictPenStatus(
            $request->integer('pen_id'),
            $overrides,
            $request->boolean('async', false),
            $request->boolean('use_cache', true)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
    public function batchFeedRecommendation(Request $request): JsonResponse
    {
        $request->validate([
            'pen_ids' => 'required|array|min:1',
            'pen_ids.*' => 'exists:hog_pens,id',
            'async' => 'boolean',
        ]);

        $result = $this->fastapi->batchPredictFeedRecommendation(
            $request->array('pen_ids'),
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
    public function batchWeightTrend(Request $request): JsonResponse
    {
        $request->validate([
            'pen_ids' => 'required|array|min:1',
            'pen_ids.*' => 'exists:hog_pens,id',
            'async' => 'boolean',
        ]);

        $result = $this->fastapi->batchPredictWeightTrend(
            $request->array('pen_ids'),
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
    public function batchPenStatus(Request $request): JsonResponse
    {
        $request->validate([
            'pen_ids' => 'required|array|min:1',
            'pen_ids.*' => 'exists:hog_pens,id',
            'async' => 'boolean',
        ]);

        $result = $this->fastapi->batchPredictPenStatus(
            $request->array('pen_ids'),
            $request->boolean('async', false)
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
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
}
