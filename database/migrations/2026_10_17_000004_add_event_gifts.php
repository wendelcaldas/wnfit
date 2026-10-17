<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->json('gift_snapshot')->nullable();
            $t->timestamp('gift_revealed_at')->nullable();
            $t->timestamp('gift_delivered_at')->nullable();
            $t->foreignId('gift_delivered_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('gift_delivered_by');
            $t->dropColumn(['gift_snapshot', 'gift_revealed_at', 'gift_delivered_at']);
        });
    }
};
