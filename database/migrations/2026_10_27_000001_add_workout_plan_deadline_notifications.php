<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_notifications', function (Blueprint $table) {
            $table->foreignId('student_workout_session_id')->nullable()->change();
            $table->foreignId('student_workout_plan_id')->nullable()->constrained('student_workout_plans')->cascadeOnDelete();
            $table->unique(['user_id', 'student_workout_plan_id', 'type'], 'staff_notifications_plan_event_unique');
        });
    }

    public function down(): void
    {
        DB::table('staff_notifications')->whereNotNull('student_workout_plan_id')->whereNull('student_workout_session_id')->delete();
        Schema::table('staff_notifications', function (Blueprint $table) {
            $table->dropUnique('staff_notifications_plan_event_unique');
            $table->dropConstrainedForeignId('student_workout_plan_id');
            $table->foreignId('student_workout_session_id')->nullable(false)->change();
        });
    }
};
