<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingPredictions extends Model
{
    protected $table = 'feeding_predictions';

    protected $fillable = [
        'hog_pen_id',
        'ml_model_id',
        'predicted_feed_amount',
        'confidence_score',
        'model_used',
        'confidence_level',
        'confidence_reason',
        'feed_recommendation',
        'feed_totals',
        'weight_trend',
        'pen_status',
        'warnings',
        'alerts',
        'suggestions',
        'fastapi_response',
        'predicted_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'feed_recommendation' => 'array',
        'feed_totals' => 'array',
        'weight_trend' => 'array',
        'pen_status' => 'array',
        'warnings' => 'array',
        'alerts' => 'array',
        'suggestions' => 'array',
        'fastapi_response' => 'array',
        'predicted_at' => 'datetime',
    ];

    public function hogPen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function mlModel()
    {
        return $this->belongsTo(MLModels::class, 'ml_model_id');
    }

    /**
     * Get the recommended feed amount
     */
    public function getRecommendedFeedAttribute(): float
    {
        return $this->feed_recommendation['recommended_feed_per_pig_per_day'] ?? $this->predicted_feed_amount ?? 0;
    }

    /**
     * Check if prediction has warnings
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Check if prediction has alerts
     */
    public function hasAlerts(): bool
    {
        return ! empty($this->alerts);
    }
}
