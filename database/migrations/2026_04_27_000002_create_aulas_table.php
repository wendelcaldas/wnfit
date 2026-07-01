<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('nome');
            $table->string('sala')->nullable();
            $table->string('instrutor', 80)->nullable();
            $table->date('data');
            $table->time('hora');
            $table->unsignedSmallInteger('capacidade')->default(15);
            $table->unsignedSmallInteger('reservas')->default(0);
            $table->timestamps();

            $table->index(['organizacao_id', 'data', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aulas');
    }
};
