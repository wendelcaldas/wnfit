<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_physical_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->date('assessed_on');
            foreach (['weight', 'height', 'body_fat', 'arm_right', 'arm_left', 'chest', 'waist', 'hip', 'thigh_right', 'thigh_left', 'calf_right', 'calf_left'] as $measure) {
                $table->decimal($measure, 7, 2)->nullable();
            }
            $table->string('method', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['aluno_id', 'assessed_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_physical_assessments');
    }
};
