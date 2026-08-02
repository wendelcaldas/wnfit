<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('titulo');
            $table->string('tipo', 40)->default('coletiva');
            $table->string('status', 20)->default('agendado');
            $table->dateTime('inicio_em');
            $table->dateTime('fim_em');
            $table->foreignId('instrutor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modalidade', 20)->default('presencial');
            $table->string('local')->nullable();
            $table->string('endereco')->nullable();
            $table->unsignedSmallInteger('capacidade')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['organizacao_id', 'inicio_em']);
            $table->index(['instrutor_id', 'inicio_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamentos');
    }
};
