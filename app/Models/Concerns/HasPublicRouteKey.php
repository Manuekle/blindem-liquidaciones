<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** Public, random (UUID v4, not guessable or sequential) identifier for URLs. The integer id stays internal (foreign keys, joins). */
trait HasPublicRouteKey
{
    public static function bootHasPublicRouteKey(): void
    {
        static::replicating(function ($model) {
            $model->public_id = (string) Str::uuid();
        });
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
