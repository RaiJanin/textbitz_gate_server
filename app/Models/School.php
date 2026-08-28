<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['name', 'timezone', 'attendance_cutoff_time', 'ingest_token', 'contact_phone', 'contact_email'])]
#[Hidden(['ingest_token'])]
class School extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            if (! $school->ingest_token) {
                $school->ingest_token = Str::random(48);
            }
        });
    }

    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * "Now" expressed in the school's local timezone.
     */
    public function localNow(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    /**
     * The attendance cutoff for a given day, as a timezone-aware instant.
     */
    public function cutoffFor(Carbon $day): Carbon
    {
        [$hour, $minute, $second] = array_pad(explode(':', (string) $this->attendance_cutoff_time), 3, 0);

        return $day->copy()->setTimezone($this->timezone)->setTime((int) $hour, (int) $minute, (int) $second);
    }
}
