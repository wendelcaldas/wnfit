<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->unique()->constrained('organizacoes')->cascadeOnDelete();
            $table->string('provedor', 30)->default('twilio');
            $table->string('whatsapp_from', 40)->nullable();
            $table->string('charge_template_key', 80)->default('charge_reminder_default');
            $table->string('charge_template_sid')->nullable();
            $table->text('charge_message_body');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_configuracoes');
    }
};
