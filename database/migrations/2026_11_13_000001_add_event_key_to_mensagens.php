<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', fn (Blueprint $table) => $table->string('event_key', 100)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('mensagens', fn (Blueprint $table) => $table->dropColumn('event_key'));
    }
};
