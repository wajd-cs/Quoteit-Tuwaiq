<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'bio', 'contact_link', 'locale', 'avatar_path', 'preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likedQuotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class, 'likes')->withTimestamps();
    }

    public function bookmarkedQuotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class, 'bookmarks')->withTimestamps();
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function quotePicks(): HasMany
    {
        return $this->hasMany(QuotePick::class);
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->whereKey($user->id)->exists();
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name));
        $first = mb_substr($words[0] ?? '', 0, 1);
        $second = isset($words[1]) ? mb_substr($words[1], 0, 1) : '';

        return Str::upper($first.$second);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('users.avatar', [$this, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    public function contactHref(): ?string
    {
        if (blank($this->contact_link)) {
            return null;
        }

        return filter_var($this->contact_link, FILTER_VALIDATE_EMAIL)
            ? 'mailto:'.$this->contact_link
            : $this->contact_link;
    }
}
