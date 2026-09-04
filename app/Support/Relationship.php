<?php

namespace App\Support;

/**
 * The relationship a guardian has to a student. Stored on `guardians.role`
 * (the guardian's default) and on the `guardian_student.relationship` pivot
 * (the authoritative per-student value).
 *
 * Not a native enum — "Parent" is a reserved word and can't be an enum case.
 */
final class Relationship
{
    public const PARENT = 'Parent';

    public const GUARDIAN = 'Guardian';

    public const DEFAULT = self::GUARDIAN;

    /** @var list<string> */
    public const VALUES = [self::PARENT, self::GUARDIAN];

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, self::VALUES, true) ? $value : self::DEFAULT;
    }
}
