<?php

namespace App\Services\FastApi;

class FastApiResponseNormalizer
{
    /**
     * Normalize single prediction responses into one stable structure.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizePrediction(string $predictionType, array $response): array
    {
        $payload = $this->unwrapPredictionPayload($response);
        $feedRecommendation = $this->arrayValue($payload, 'feed_recommendation');

        return [
            'prediction_type' => $predictionType,
            'model_used' => $this->stringValue($payload, 'model_used'),
            'confidence_score' => $this->floatValue(
                $feedRecommendation,
                'confidence_score',
                $this->floatValue($payload, 'confidence_score')
            ),
            'confidence_level' => $this->stringValue($payload, 'confidence_level', 'unknown'),
            'confidence_reason' => $this->stringValue($payload, 'confidence_reason'),
            'predicted_feed_amount' => $this->floatValue($feedRecommendation, 'recommended_feed_per_pig_per_day'),
            'feed_recommendation' => $feedRecommendation,
            'feed_totals' => $this->arrayValue($payload, 'feed_totals'),
            'weight_trend' => $this->normalizeWeightTrend($payload['weight_trend'] ?? []),
            'pen_status' => $this->arrayValue($payload, 'pen_status'),
            'warnings' => $this->listValue($payload, 'warnings'),
            'alerts' => $this->listValue($payload, 'alerts'),
            'suggestions' => $this->listValue($payload, 'suggestions'),
            'raw' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizeBatchPrediction(string $predictionType, array $response): array
    {
        $results = $response['results'] ?? $response['predictions'] ?? [];

        if (! is_array($results)) {
            $results = [];
        }

        return [
            'prediction_type' => $predictionType,
            'results' => array_map(fn (mixed $item): array => $this->normalizePrediction($predictionType, is_array($item) ? $item : []), $results),
            'raw' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalizeLegacyPrediction(array $response): array
    {
        return $this->unwrapPredictionPayload($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrapPredictionPayload(array $payload): array
    {
        $unwrapped = $payload;

        while (isset($unwrapped['data']) && is_array($unwrapped['data'])) {
            $unwrapped = $unwrapped['data'];
        }

        if (isset($unwrapped['prediction']) && is_array($unwrapped['prediction'])) {
            $unwrapped = $unwrapped['prediction'];
        }

        return $unwrapped;
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    private function normalizeWeightTrend(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    private function listValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, ?string $default = null): ?string
    {
        $value = $payload[$key] ?? $default;

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function floatValue(array $payload, string $key, float $default = 0.0): float
    {
        $value = $payload[$key] ?? $default;

        return round((float) $value, 2);
    }
}
