<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `x08` — the Filament admin-panel accounts (school staff / super-admins),
 * kept completely separate from `users`, which is now guardian/student
 * (client-app) accounts only. `school_id` null ⇒ super-admin (every school).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x08', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('password');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x08');
    }
};
