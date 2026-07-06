<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treino_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treino_id')->constrained('treinos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('foco')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('treino_exercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treino_dia_id')->constrained('treino_dias')->cascadeOnDelete();
            $table->string('nome');
            $table->string('grupo_muscular')->nullable();
            $table->unsignedTinyInteger('series')->default(3);
            $table->string('repeticoes', 30)->default('10');
            $table->string('carga', 50)->nullable();
            $table->unsignedSmallInteger('descanso_segundos')->default(60);
            $table->text('observacoes')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treino_exercicios');
        Schema::dropIfExists('treino_dias');
    }
};
