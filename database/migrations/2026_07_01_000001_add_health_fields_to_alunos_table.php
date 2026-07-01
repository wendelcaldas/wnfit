<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('contato_emergencia')->nullable()->after('telefone');
            $table->string('telefone_emergencia', 20)->nullable()->after('contato_emergencia');
            $table->text('restricoes_medicas')->nullable()->after('observacoes');
            $table->text('lesoes')->nullable()->after('restricoes_medicas');
            $table->text('medicamentos')->nullable()->after('lesoes');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', fn (Blueprint $table) => $table->dropColumn([
            'contato_emergencia', 'telefone_emergencia', 'restricoes_medicas', 'lesoes', 'medicamentos',
        ]));
    }
};
