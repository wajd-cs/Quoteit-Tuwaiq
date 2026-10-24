<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name', 'name_ar', 'icon', 'sort'])]
class Goal extends Model
{
    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Name in the current language. */
    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
