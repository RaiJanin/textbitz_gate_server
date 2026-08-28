<?php

use App\Models\Gate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Account-level channel — weekly summaries, absence flags, link events.
| Named "user.{id}" where {id} is the server user id (the client's remote_id).
*/
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

/*
| Per-student tap stream — every linked guardian + the student's own account.
*/
Broadcast::channel('student.{student}', function (User $user, Student $student) {
    return $user->canViewStudent($student);
});

/*
| Per-gate device health — anyone linked to a student at that gate's school
| (a future school-admin view; the parent app does not subscribe today).
*/
Broadcast::channel('gate.{gate}', function (User $user, Gate $gate) {
    return $user->schoolIds()->contains($gate->school_id);
});
