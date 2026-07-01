<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->decimal('valor', 10, 2);
            $table->date('competencia');
            $table->string('descricao')->nullable();
            $table->timestamps();

            $table->index(['organizacao_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receitas');
    }
};
