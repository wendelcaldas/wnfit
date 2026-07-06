<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('objetivo', 80);
            $table->string('nivel', 30)->default('iniciante');
            $table->unsignedTinyInteger('sessoes_semana')->default(3);
            $table->unsignedSmallInteger('duracao_semanas')->default(4);
            $table->string('status', 20)->default('rascunho');
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->index(['organizacao_id', 'status']);
            $table->index(['organizacao_id', 'objetivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treinos');
    }
};
