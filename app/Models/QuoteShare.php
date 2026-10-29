<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quote_id', 'user_id', 'channel'])]
class QuoteShare extends Model
{
    public const CHANNELS = ['copy', 'link', 'x', 'whatsapp', 'telegram', 'linkedin', 'facebook', 'instagram', 'snapchat', 'email', 'native', 'image'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
