<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', fn (Blueprint $t) => $t->string('perfil_tipo', 20)->default('aluno')->index());
        Schema::table('events', fn (Blueprint $t) => $t->string('audience', 20)->default('open'));
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->dropUnique(['event_id', 'phone']);
            $t->string('phone', 30)->nullable()->change();
            $t->dateTime('privacy_accepted_at')->nullable()->change();
            $t->string('source', 20)->default('public');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Registration phones may now be shared or absent; don't restore the old unique constraint.
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('created_by');
            $t->dropColumn('source');
        });
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('audience'));
        Schema::table('alunos', fn (Blueprint $t) => $t->dropColumn('perfil_tipo'));
    }
};
