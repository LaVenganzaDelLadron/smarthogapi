<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Logs all webhook delivery attempts
 *
 * Tracks webhook notifications sent to external systems
 */
class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'url',
        'event',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

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
