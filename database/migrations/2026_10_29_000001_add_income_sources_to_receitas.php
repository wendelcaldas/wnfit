<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->string('origem', 20)->default('avulso');
            $table->string('metodo', 30)->default('PIX');
            $table->uuid('request_id')->nullable()->unique();
            $table->string('external_id')->nullable();
            $table->unique(['organizacao_id', 'origem', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropUnique(['organizacao_id', 'origem', 'external_id']);
            $table->dropUnique(['request_id']);
            $table->dropColumn(['origem', 'metodo', 'request_id', 'external_id']);
        });
    }
};
