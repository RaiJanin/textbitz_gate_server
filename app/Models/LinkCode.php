<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['school_id', 'student_id', 'code', 'default_relationship', 'expires_at', 'consumed_at', 'consumed_by_guardian_id'])]
class LinkCode extends Model
{
    /** @use HasFactory<\Database\Factories\LinkCodeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (LinkCode $linkCode) {
            if (! $linkCode->code) {
                $linkCode->code = strtoupper(Str::random(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function consumedByGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'consumed_by_guardian_id');
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isUsable(): bool
    {
        return is_null($this->consumed_at)
            && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    /**
     * 'usable' | 'consumed' | 'expired' — for admin badges and the printed slip.
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            ! is_null($this->consumed_at) => 'consumed',
            ! is_null($this->expires_at) && $this->expires_at->isPast() => 'expired',
            default => 'usable',
        });
    }
}
