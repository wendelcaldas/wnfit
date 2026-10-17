<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->json('modules')->nullable());
        Schema::table('event_registrations', fn (Blueprint $t) => $t->timestamp('welcomed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('modules'));
        Schema::table('event_registrations', fn (Blueprint $t) => $t->dropColumn('welcomed_at'));
    }
};
