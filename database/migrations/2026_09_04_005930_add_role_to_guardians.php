<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guardian's default relationship to their students ('Parent' | 'Guardian').
 * Seeds `guardian_student.relationship` when a code is redeemed; the pivot then
 * holds the authoritative per-student value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('role')->default('Guardian')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
