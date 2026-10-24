<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_ar', 'slug', 'icon'])]
class Category extends Model
{
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function goals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class);
    }

    /** Name in the current language. */
    public function label(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar ? $this->name_ar : $this->name;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
