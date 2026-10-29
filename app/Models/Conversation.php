<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_one_id', 'user_two_id', 'last_message_at'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** The conversation between two people, made the first time they talk. */
    public static function between(User $a, User $b): self
    {
        return static::firstOrCreate([
            'user_one_id' => min($a->id, $b->id),
            'user_two_id' => max($a->id, $b->id),
        ]);
    }

    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id));
    }

    public function includes(?User $user): bool
    {
        return $user !== null && in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function otherThan(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->userTwo : $this->userOne;
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
