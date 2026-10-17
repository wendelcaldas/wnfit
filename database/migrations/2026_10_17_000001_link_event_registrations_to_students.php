<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->restrictOnDelete();
            $table->unique(['event_id', 'aluno_id']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'aluno_id']);
            $table->dropConstrainedForeignId('aluno_id');
        });
    }
};
