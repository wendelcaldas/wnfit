<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento_aluno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->string('status', 20)->default('confirmado');
            $table->timestamps();

            $table->unique(['agendamento_id', 'aluno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_aluno');
    }
};
