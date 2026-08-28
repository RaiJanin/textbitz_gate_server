<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('default_relationship')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_by_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_codes');
    }
};
