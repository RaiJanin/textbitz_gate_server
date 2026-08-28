<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'sometimes|string|max:32',
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'last_used_at' => now(),
            ],
        );

        return response()->json(['registered' => true, 'id' => $token->id], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['removed' => true]);
    }
}
