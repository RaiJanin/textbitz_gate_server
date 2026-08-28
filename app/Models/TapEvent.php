<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'gate_id', 'direction', 'tapped_at', 'is_late', 'source', 'synced_at'])]
class TapEvent extends Model
{
    /** @use HasFactory<\Database\Factories\TapEventFactory> */
    use HasFactory;

    const DIRECTION_IN = 'in';
    const DIRECTION_OUT = 'out';

    protected function casts(): array
    {
        return [
            'tapped_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function scopeForDay(Builder $query, \Illuminate\Support\Carbon $day): Builder
    {
        return $query->whereBetween('tapped_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }
}
