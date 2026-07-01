<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->date('data_nascimento')->nullable()->after('telefone');
            $table->string('genero', 30)->nullable()->after('data_nascimento');
            $table->string('cpf', 20)->nullable()->after('genero');
            $table->string('rg', 30)->nullable()->after('cpf');
            $table->string('profissao')->nullable()->after('rg');
            $table->string('endereco')->nullable()->after('profissao');
            $table->string('numero', 20)->nullable()->after('endereco');
            $table->string('complemento')->nullable()->after('numero');
            $table->string('bairro')->nullable()->after('complemento');
            $table->string('cidade')->nullable()->after('bairro');
            $table->string('estado', 2)->nullable()->after('cidade');
            $table->string('cep', 20)->nullable()->after('estado');
            $table->decimal('peso', 5, 2)->nullable()->after('status');
            $table->unsignedSmallInteger('altura')->nullable()->after('peso');
            $table->string('como_conheceu')->nullable()->after('altura');
            $table->string('unidade')->nullable()->after('como_conheceu');
            $table->text('observacoes')->nullable()->after('unidade');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn([
                'data_nascimento',
                'genero',
                'cpf',
                'rg',
                'profissao',
                'endereco',
                'numero',
                'complemento',
                'bairro',
                'cidade',
                'estado',
                'cep',
                'peso',
                'altura',
                'como_conheceu',
                'unidade',
                'observacoes',
            ]);
        });
    }
};
