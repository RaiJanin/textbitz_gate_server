<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['school_id', 'full_name', 'grade', 'section', 'rfid_uid', 'avatar_path'])]
class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function tapEvents(): HasMany
    {
        return $this->hasMany(TapEvent::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function studentAccount(): HasOne
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function latestTapOn(Carbon $day): ?TapEvent
    {
        return $this->tapEvents()
            ->whereBetween('tapped_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderByDesc('tapped_at')
            ->orderByDesc('id')
            ->first();
    }
}
