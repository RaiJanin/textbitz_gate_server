<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'max:255', 'unique:'.User::class],
            'phone_number' => ['required', 'string', new PhilippineMobileNumber, 'unique:'.User::class],
            'password' => 'required|string|min:8',
            'device_name' => 'required|string',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
            ]);

            // Every new account is a guardian by default so the app has a
            // profile + preferences to read immediately after sign-up.
            $user->guardian()->create([
                'name' => $user->name,
                'phone' => $user->phone_number,
            ]);
            $user->preferencesFor(NotificationPreference::ROLE_GUARDIAN);

            return $user;
        });

        return response()->json([
            'user' => $user,
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', new PhilippineMobileNumber],
            'password' => 'required|string',
            'device_name' => 'required|string',
        ]);

        $user = User::where('phone_number', $validated['phone_number'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone_number' => ['Invalid credentials.'],
            ]);
        }

        $user->tokens()->where('name', $validated['device_name'])->delete();

        return response()->json([
            'user' => $user,
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
