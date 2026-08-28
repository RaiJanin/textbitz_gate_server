<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = collect($user->roles())
            ->map(fn (string $role) => $user->preferencesFor($role));

        return response()->json([
            'preferences' => NotificationPreferenceResource::collection($preferences),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'role' => 'required|in:'.implode(',', [
                NotificationPreference::ROLE_GUARDIAN,
                NotificationPreference::ROLE_STUDENT,
            ]),
            'arrival' => 'sometimes|boolean',
            'departure' => 'sometimes|boolean',
            'late_alert' => 'sometimes|boolean',
            'weekly_summary' => 'sometimes|boolean',
        ]);

        abort_unless(in_array($validated['role'], $user->roles(), true), 403, 'Account does not hold that role.');

        $preference = $user->preferencesFor($validated['role']);
        $preference->fill(collect($validated)->except('role')->all())->save();

        return response()->json([
            'preference' => new NotificationPreferenceResource($preference),
        ]);
    }
}
