<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tap_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->timestamp('tapped_at');
            $table->boolean('is_late')->default(false);
            $table->string('source')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'tapped_at']);
            $table->index(['gate_id', 'tapped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tap_events');
    }
};
