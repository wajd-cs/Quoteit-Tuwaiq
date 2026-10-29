<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'content', 'author', 'style', 'expires_at'])]
class Story extends Model
{
    /** How long a story can stay up, in hours. */
    public const LIFETIMES = [12, 24];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'style' => 'array'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /** Seconds on screen: long quotes stay a bit longer so they can be read. */
    public function seconds(): int
    {
        return mb_strlen($this->content) > 80 ? 15 : 10;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'story_likes')->withTimestamps();
    }
}
