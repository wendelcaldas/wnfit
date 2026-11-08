<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->boolean('publicar_vagas')->default(false);
            $table->index(['organizacao_id', 'publicar_vagas', 'status', 'inicio_em'], 'agendamentos_vagas_publicas');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropIndex('agendamentos_vagas_publicas');
            $table->dropColumn('publicar_vagas');
        });
    }
};
