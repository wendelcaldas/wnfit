<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->boolean('is_paid')->default(false);
            $t->decimal('price', 10, 2)->default(0);
        });
        Schema::table('cobrancas', function (Blueprint $t) {
            $t->foreignId('event_registration_id')->nullable()->unique()->constrained('event_registrations')->restrictOnDelete();
            $t->string('origem', 20)->default('mensalidade');
            $t->string('descricao')->nullable();
            $t->boolean('refund_review_required')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('event_registration_id');
            $t->dropColumn(['origem', 'descricao', 'refund_review_required']);
        });
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['is_paid', 'price']));
    }
};
