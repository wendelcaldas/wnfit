<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('objetivo', 80)->default('Saude');
            $table->string('treinador', 80)->nullable();
            $table->string('plano', 80)->default('Plano Mensal');
            $table->date('vencimento')->nullable();
            $table->string('status', 30)->default('ativo');
            $table->timestamps();

            $table->index(['organizacao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
