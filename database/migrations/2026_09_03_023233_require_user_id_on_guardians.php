<?php

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every guardian profile must belong to a client-app account: `guardians.user_id`
 * becomes NOT NULL. Any pre-existing orphan is given (or matched to) a User first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Guardian::whereNull('user_id')->get()->each(function (Guardian $guardian): void {
            $phone = $guardian->phone ?: '+639'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

            $user = User::firstOrCreate(
                ['phone_number' => $phone],
                [
                    'name' => $guardian->name,
                    'email' => $guardian->email,
                    'password' => Hash::make(Str::password(16)),
                ],
            );

            // If that user already has a guardian, this orphan is redundant.
            if ($user->guardian && $user->guardian->isNot($guardian)) {
                $guardian->delete();

                return;
            }

            $guardian->forceFill(['user_id' => $user->id])->save();
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }
};
