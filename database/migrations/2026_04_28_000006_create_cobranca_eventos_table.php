<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained('cobrancas')->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('descricao');
            $table->timestamp('ocorrido_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_eventos');
    }
};
