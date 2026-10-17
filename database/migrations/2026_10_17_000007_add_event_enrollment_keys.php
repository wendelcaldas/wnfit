<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->uuid('enrollment_key')->nullable();
            $t->unique(['event_id', 'enrollment_key']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->dropUnique(['event_id', 'enrollment_key']);
            $t->dropColumn('enrollment_key');
        });
    }
};
