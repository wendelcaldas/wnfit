<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('nome');
            $table->decimal('valor_mensal', 10, 2);
            $table->string('ciclo', 30)->default('mensal');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['organizacao_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
