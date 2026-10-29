<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativa')->default(true);
            $table->boolean('padrao')->default(false);
            $table->timestamps();
            $table->unique(['organizacao_id', 'nome']);
        });
        Schema::table('alunos', fn (Blueprint $table) => $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete());

        DB::table('organizacoes')->orderBy('id')->chunkById(100, function ($organizations) {
            foreach ($organizations as $org) {
                DB::transaction(function () use ($org) {
                    $default = DB::table('unidades')->insertGetId(['organizacao_id' => $org->id, 'nome' => 'Unidade principal', 'ativa' => true, 'padrao' => true, 'created_at' => now(), 'updated_at' => now()]);
                    // Keep every existing label; no student is moved to a differently named unit.
                    $names = DB::table('alunos')->where('organizacao_id', $org->id)->whereNotNull('unidade')->distinct()->pluck('unidade');
                    foreach ($names as $name) {
                        if (trim($name) === '') continue;
                        $id = DB::table('unidades')->where('organizacao_id', $org->id)->where('nome', $name)->value('id');
                        $id ??= DB::table('unidades')->insertGetId(['organizacao_id' => $org->id, 'nome' => $name, 'ativa' => true, 'padrao' => false, 'created_at' => now(), 'updated_at' => now()]);
                        DB::table('alunos')->where('organizacao_id', $org->id)->where('unidade', $name)->update(['unidade_id' => $id]);
                    }
                    DB::table('alunos')->where('organizacao_id', $org->id)->whereNull('unidade_id')->update(['unidade_id' => $default]);
                });
            }
        });
    }

    public function down(): void
    {
        Schema::table('alunos', fn (Blueprint $table) => $table->dropConstrainedForeignId('unidade_id'));
        Schema::dropIfExists('unidades');
    }
};
