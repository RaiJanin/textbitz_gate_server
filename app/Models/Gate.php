<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'name', 'status', 'last_seen_at'])]
class Gate extends Model
{
    /** @use HasFactory<\Database\Factories\GateFactory> */
    use HasFactory;

    const STATUS_ONLINE = 'online';
    const STATUS_OFFLINE = 'offline';

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function tapEvents(): HasMany
    {
        return $this->hasMany(TapEvent::class);
    }
}
