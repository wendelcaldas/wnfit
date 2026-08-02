<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->uuid('serie_id')->nullable()->after('organizacao_id');
            $table->string('recorrencia_frequencia', 20)->nullable()->after('tipo');
            $table->date('recorrencia_ate')->nullable()->after('fim_em');

            $table->index(['organizacao_id', 'serie_id']);
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropIndex(['organizacao_id', 'serie_id']);
            $table->dropColumn(['serie_id', 'recorrencia_frequencia', 'recorrencia_ate']);
        });
    }
};
