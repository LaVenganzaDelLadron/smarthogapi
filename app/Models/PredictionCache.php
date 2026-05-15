<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Caches ML prediction results in database
 *
 * Provides an alternative to Redis cache for persistent storage
 * of prediction results across process restarts
 */
class PredictionCache extends Model
{
    use UserOwned;

    protected $table = 'prediction_cache';

    protected $fillable = [
        'prediction_type',
        'pen_id',
        'cache_key',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function pen(): BelongsTo
    {
        return $this->belongsTo(Hogpens::class, 'pen_id');
    }

    /**
     * Get cache for a prediction
     */
    public static function get(string $type, int $penId): ?array
    {
        $record = static::query()
            ->where('prediction_type', $type)
            ->where('pen_id', $penId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        return $record?->data;
    }

    /**
     * Store cache entry
     */
    public static function store(string $type, int $penId, array $data, int $hoursToExpire = 24): void
    {
        static::query()->create([
            'prediction_type' => $type,
            'pen_id' => $penId,
            'cache_key' => "{$type}:pen_{$penId}",
            'data' => $data,
            'expires_at' => now()->addHours($hoursToExpire),
        ]);
    }

    /**
     * Clear expired cache entries
     */
    public static function clearExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
