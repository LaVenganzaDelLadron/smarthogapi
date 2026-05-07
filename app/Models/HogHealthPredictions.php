<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HogHealthPredictions extends Model
{
    protected $table = 'hog_health_predictions';

    protected $fillable = [
        'hog_id',
        'ml_model_id',
        'predicted_status',
        'risk_score',
        'model_used',
        'confidence_level',
        'confidence_reason',
        'weight_trend',
        'pen_status',
        'warnings',
        'metrics',
        'fastapi_response',
        'predicted_at',
    ];

    protected $casts = [
        'risk_score' => 'float',
        'weight_trend' => 'array',
        'pen_status' => 'array',
        'warnings' => 'array',
        'metrics' => 'array',
        'fastapi_response' => 'array',
        'predicted_at' => 'datetime',
    ];

    public function hog()
    {
        return $this->belongsTo(Hogs::class, 'hog_id');
    }

    public function mlModel()
    {
        return $this->belongsTo(MLModels::class, 'ml_model_id');
    }

    /**
     * Get the pen status from prediction
     */
    public function getPenStatusAttribute(): ?array
    {
        return $this->attributes['pen_status'] ?? null;
    }

    /**
     * Check if weight prediction has trends
     */
    public function hasWeightTrend(): bool
    {
        return ! empty($this->weight_trend);
    }

    /**
     * Get latest weight prediction
     */
    public function getLatestWeightPredictionAttribute(): ?array
    {
        if (empty($this->weight_trend)) {
            return null;
        }

        $trends = $this->weight_trend;

        return end($trends) ?: null;
    }

    /**
     * Check if prediction has warnings
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }
}
