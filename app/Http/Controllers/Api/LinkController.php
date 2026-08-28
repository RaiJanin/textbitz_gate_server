<?php

namespace App\Http\Controllers\Api;

use App\Events\GuardianLinkedToStudent;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\LinkCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'relationship' => 'sometimes|string|max:40',
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

        $relationship = $validated['relationship']
            ?? $linkCode->default_relationship
            ?? 'Guardian';

        DB::transaction(function () use ($guardian, $linkCode, $relationship) {
            $guardian->students()->syncWithoutDetaching([
                $linkCode->student_id => ['relationship' => $relationship],
            ]);

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

        $linkCode->student->loadMissing('school');

        return response()->json([
            'linked' => true,
            'student' => new StudentResource($linkCode->student),
        ], 201);
    }
}
