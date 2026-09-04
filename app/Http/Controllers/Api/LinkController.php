<?php

namespace App\Http\Controllers\Api;

use App\Events\GuardianLinkedToStudent;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\LinkCode;
use App\Models\Student;
use App\Support\Relationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    /**
     * Complete a guardian↔student link using a school-issued code. Linking is
     * never open self-service — the code proves the school authorised it.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'relationship' => ['sometimes', 'nullable', Rule::in(Relationship::VALUES)],
        ]);

        $user = $request->user();

        $guardian = $user->guardian()->firstOrCreate([], [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone_number,
        ]);

        $linkCode = LinkCode::with('student.school')
            ->where('code', strtoupper(trim($validated['code'])))
            ->first();

        if (! $linkCode || ! $linkCode->isUsable()) {
            return response()->json([
                'message' => 'That link code is invalid or has expired.',
                'error' => 'invalid_code',
            ], 422);
        }

        $relationship = Relationship::normalize(
            $validated['relationship']
            ?? $guardian->role
            ?? $linkCode->default_relationship
        );

        DB::transaction(function () use ($guardian, $linkCode, $relationship) {
            $guardian->students()->syncWithoutDetaching([
                $linkCode->student_id => ['relationship' => $relationship],
            ]);

            // Keep the guardian's default in step with their latest choice.
            $guardian->forceFill(['role' => $relationship])->save();

            $linkCode->forceFill([
                'consumed_at' => now(),
                'consumed_by_guardian_id' => $guardian->id,
            ])->save();

            $user = $guardian->user;
            if ($user) {
                $user->preferencesFor(\App\Models\NotificationPreference::ROLE_GUARDIAN);
            }
        });

        GuardianLinkedToStudent::dispatch($guardian, $linkCode->student);

        $linked = $guardian->students()->with('school')->find($linkCode->student_id);

        return response()->json([
            'linked' => true,
            'student' => new StudentResource($linked),
        ], 201);
    }

    /**
     * Change the caller's relationship to a student they're already linked to
     * (per-student override — does not touch the guardian's default `role`).
     */
    public function updateRelationship(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'relationship' => ['required', Rule::in(Relationship::VALUES)],
        ]);

        $guardian = $request->user()->guardian;

        abort_unless($guardian && $guardian->students()->whereKey($student->id)->exists(), 403);

        $guardian->students()->updateExistingPivot($student->id, [
            'relationship' => $validated['relationship'],
        ]);

        $updated = $guardian->students()->with('school')->find($student->id);

        return response()->json([
            'updated' => true,
            'student' => new StudentResource($updated),
        ]);
    }
}
