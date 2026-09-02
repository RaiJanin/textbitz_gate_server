<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Constrains a resource whose model has a direct `school_id` column to the
 * signed-in staff member's school. Super-admins (no school) see everything.
 */
trait ScopesToSchool
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin() && $user->school_id) {
            $query->where('school_id', $user->school_id);
        }

        return $query;
    }
}
