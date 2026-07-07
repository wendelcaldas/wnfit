<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aluno_treino', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('treino_id')->constrained('treinos')->cascadeOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['aluno_id', 'treino_id']);
            $table->index(['aluno_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aluno_treino');
    }
};
