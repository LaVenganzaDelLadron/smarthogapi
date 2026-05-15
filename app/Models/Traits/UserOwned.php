<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait UserOwned
{
    public function scopeOwnedByUser(Builder $query, int $userId): Builder
    {
        $relationPath = $this->ownerRelationPath();

        if ($relationPath === null) {
            return $query;
        }

        return $query->whereHas($relationPath, fn (Builder $query) => $query->where('user_id', $userId));
    }

    public function belongsToUser(int $userId): bool
    {
        $relationPath = $this->ownerRelationPath();

        if ($relationPath === null) {
            return false;
        }

        return static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->ownedByUser($userId)
            ->exists();
    }

    protected function ownerRelationPath(): ?string
    {
        if (method_exists($this, 'user')) {
            return 'user';
        }

        if (method_exists($this, 'farm')) {
            return 'farm';
        }

        if (method_exists($this, 'hogpen')) {
            return 'hogpen.farm';
        }

        if (method_exists($this, 'hog')) {
            return 'hog.hogpen.farm';
        }

        if (method_exists($this, 'sensor')) {
            return 'sensor.hogpen.farm';
        }

        if (method_exists($this, 'feeder')) {
            return 'feeder.hogpen.farm';
        }

        if (method_exists($this, 'iotDevice')) {
            return 'iotDevice.hogpen.farm';
        }

        return null;
    }
}
