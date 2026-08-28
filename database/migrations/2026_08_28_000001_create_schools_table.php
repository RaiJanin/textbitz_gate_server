<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('Asia/Manila');
            $table->time('attendance_cutoff_time')->default('07:45:00');
            $table->string('ingest_token', 64)->unique();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
