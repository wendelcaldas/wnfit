<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagem_configuracoes', function (Blueprint $table) {
            $table->json('event_preferences')->nullable();
            $table->unsignedTinyInteger('overdue_delay_days')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('mensagem_configuracoes', function (Blueprint $table) {
            $table->dropColumn(['event_preferences', 'overdue_delay_days']);
        });
    }
};
