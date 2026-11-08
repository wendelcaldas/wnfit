<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizacoes', function (Blueprint $table) {
            $table->json('modules')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_wnfit_internal')->default(false);
        });

        // Grant the existing account. New accounts never inherit access from an email address.
        DB::table('users')->where('email', 'wendelsouza.dev@gmail.com')->update(['is_wnfit_internal' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_wnfit_internal'));
        Schema::table('organizacoes', fn (Blueprint $table) => $table->dropColumn('modules'));
    }
};
