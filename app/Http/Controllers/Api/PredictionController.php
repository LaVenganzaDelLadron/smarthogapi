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
     *   "pig_age_days": 30,          // optional override
     *   "avg_weight_kg": 25.5,       // optional override
     *   ...
     * }
     */
    public function feedRecommendation(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
        ]);

        $overrides = $request->except('pen_id');

        $result = $this->fastapi->predictFeedRecommendation(
            $request->integer('pen_id'),
            $overrides
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
            ], 400);
        }

        return response()->json([
            'prediction_id' => $result['prediction_id'],
            'data' => $result['data'],
        ]);
    }

    /**
     * POST /api/predictions/weight-trend
     * Get weight trend prediction for a pen
     */
    public function weightTrend(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
        ]);

        $overrides = $request->except('pen_id');

        $result = $this->fastapi->predictWeightTrend(
            $request->integer('pen_id'),
            $overrides
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
            ], 400);
        }

        return response()->json([
            'prediction_id' => $result['prediction_id'],
            'data' => $result['data'],
        ]);
    }

    /**
     * POST /api/predictions/pen-status
     * Classify pen status
     */
    public function penStatus(Request $request): JsonResponse
    {
        $request->validate([
            'pen_id' => 'required|exists:hog_pens,id',
        ]);

        $overrides = $request->except('pen_id');

        $result = $this->fastapi->predictPenStatus(
            $request->integer('pen_id'),
            $overrides
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
            ], 400);
        }

        return response()->json([
            'prediction_id' => $result['prediction_id'],
            'data' => $result['data'],
        ]);
    }

    /**
     * POST /api/predictions/batch/feed-recommendation
     * Batch predict feed recommendations for multiple pens
     *
     * Request:
     * {
     *   "pen_ids": [1, 2, 3]
     * }
     */
    public function batchFeedRecommendation(Request $request): JsonResponse
    {
        $request->validate([
            'pen_ids' => 'required|array|min:1',
            'pen_ids.*' => 'exists:hog_pens,id',
        ]);

        $result = $this->fastapi->batchPredictFeedRecommendation(
            $request->array('pen_ids')
        );

        if (! $result['success']) {
            return response()->json([
                'error' => $result['error'],
                'type' => 'prediction_error',
            ], 400);
        }

        return response()->json([
            'count' => $result['count'],
            'data' => $result['data'],
        ]);
    }
}
