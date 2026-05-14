<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'device_credential_id',
        'token_hash',
        'client_id',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceCredential(): BelongsTo
    {
        return $this->belongsTo(DeviceCredential::class);
    }

    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>=', now());
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at?->isFuture();
    }
}
