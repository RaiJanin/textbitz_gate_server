<?php

namespace App\Actions;

use App\Models\LinkCode;
use App\Models\Student;
use App\Support\Relationship;

/**
 * Issues (and revokes) the school-authorised codes a guardian redeems in the
 * app to link to a student. Used by the Filament admin panel and covered by
 * tests directly, independent of the UI.
 */
class IssueLinkCode
{
    /**
     * Mint a fresh code for a student. Any earlier still-usable code for the
     * same student is expired first, so only one code is ever live per student.
     */
    public static function run(Student $student, string $relationship = Relationship::DEFAULT, int $validForDays = 30): LinkCode
    {
        LinkCode::query()
            ->where('student_id', $student->id)
            ->usable()
            ->update(['expires_at' => now()]);

        return LinkCode::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'default_relationship' => Relationship::normalize($relationship),
            'expires_at' => $validForDays > 0 ? now()->addDays($validForDays) : null,
        ]);
    }

    /**
     * Kill an unused code so it can no longer be redeemed. A code that a
     * guardian has already consumed is left untouched (unlinking is a separate,
     * deliberate action on the guardian).
     */
    public static function revoke(LinkCode $code): void
    {
        if ($code->isUsable()) {
            $code->update(['expires_at' => now()]);
        }
    }
}
