<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->nullable()->constrained('organizacoes')->cascadeOnDelete();
            $table->string('nome');
            $table->string('grupo_muscular', 100);
            $table->string('grupo_secundario', 100)->nullable();
            $table->string('equipamento', 100)->nullable();
            $table->string('categoria', 50)->default('musculacao');
            $table->string('nivel', 30)->default('todos');
            $table->text('instrucoes')->nullable();
            $table->text('cuidados')->nullable();
            $table->string('imagem_url')->nullable();
            $table->string('video_url')->nullable();
            $table->string('origem', 50)->default('wnfit');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['organizacao_id', 'ativo']);
            $table->index(['grupo_muscular', 'equipamento']);
        });

        Schema::table('treino_exercicios', function (Blueprint $table) {
            $table->foreignId('exercicio_id')->nullable()->after('treino_dia_id')->constrained('exercicios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treino_exercicios', fn (Blueprint $table) => $table->dropConstrainedForeignId('exercicio_id'));
        Schema::dropIfExists('exercicios');
    }
};
