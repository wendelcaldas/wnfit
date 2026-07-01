<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('assinatura_id')->nullable()->constrained('assinaturas')->nullOnDelete();
            $table->string('competencia', 7);
            $table->date('vencimento');
            $table->decimal('valor', 10, 2);
            $table->string('status', 30)->default('pendente');
            $table->string('forma_pagamento', 30)->default('PIX');
            $table->timestamp('pago_em')->nullable();
            $table->string('link_pagamento')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            $table->index(['organizacao_id', 'status', 'vencimento']);
            $table->unique(['assinatura_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
