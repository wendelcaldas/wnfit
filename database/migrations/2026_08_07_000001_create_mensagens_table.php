<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->nullOnDelete();
            $table->foreignId('cobranca_id')->nullable()->constrained('cobrancas')->nullOnDelete();
            $table->string('canal', 30)->default('whatsapp');
            $table->string('provedor', 30)->default('twilio');
            $table->string('tipo', 60);
            $table->string('status', 30)->default('pendente');
            $table->string('destinatario', 40);
            $table->string('remetente', 40)->nullable();
            $table->string('template_key', 80)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('conteudo');
            $table->json('payload')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('entregue_em')->nullable();
            $table->timestamps();

            $table->index(['organizacao_id', 'status', 'created_at']);
            $table->index(['cobranca_id', 'tipo']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
