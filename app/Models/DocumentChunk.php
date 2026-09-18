<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'position',
        'content',
        'embedding',
    ];

    protected function embedding(): Attribute
    {
        return Attribute::make(
            set: fn (?array $value): ?string => $value === null
                ? null
                : '['.implode(',', $value).']',
        );
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
