<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_workout_session_id')->constrained('student_workout_sessions')->cascadeOnDelete();
            $table->string('type', 60);
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'student_workout_session_id', 'type'], 'staff_notifications_event_unique');
            $table->index(['organizacao_id', 'user_id', 'read_at', 'id'], 'staff_notifications_inbox_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
    }
};
