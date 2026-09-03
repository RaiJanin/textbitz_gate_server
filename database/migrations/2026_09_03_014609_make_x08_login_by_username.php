<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin panel logs in by `name` (username) instead of email, so `name` must be
 * unique and `email` becomes optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x08', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('x08', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
