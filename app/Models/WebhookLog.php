<?php

namespace App\Models;

use App\Models\Traits\UserOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logs all webhook delivery attempts
 *
 * Tracks webhook notifications sent to external systems
 */
class WebhookLog extends Model
{
    use UserOwned;

    protected $table = 'webhook_logs';

    protected $fillable = [
        'farm_id',
        'url',
        'event',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farms::class, 'farm_id');
    }

    /**
     * Get logs for a specific event
     */
    public static function forEvent(string $event)
    {
        return static::where('event', $event)->latest();
    }

    /**
     * Get failed webhook deliveries
     */
    public static function failed()
    {
        return static::where('status', 'failed')->latest();
    }
}
