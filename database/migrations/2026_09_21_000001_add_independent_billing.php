<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->date('recorrencia_inicio')->nullable();
            $table->date('encerramento_em')->nullable();
            $table->date('pausa_em')->nullable();
        });
        Schema::create('cobranca_mensagem', function (Blueprint $table) {
            $table->foreignId('cobranca_id')->constrained('cobrancas')->cascadeOnDelete();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->primary(['cobranca_id', 'mensagem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_mensagem');
        Schema::table('assinaturas', fn (Blueprint $table) => $table->dropColumn(['recorrencia_inicio', 'encerramento_em', 'pausa_em']));
    }
};
