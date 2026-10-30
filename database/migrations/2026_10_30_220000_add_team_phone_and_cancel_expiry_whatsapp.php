<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizacao_usuario', fn (Blueprint $table) => $table->string('telefone', 20)->nullable());
        DB::table('organizacoes')->whereNotNull('telefone_contato')->orderBy('id')->chunkById(100, function ($organizations) {
            foreach ($organizations as $organization) {
                DB::table('organizacao_usuario')->where('organizacao_id', $organization->id)
                    ->where('papel', 'proprietario')->whereNull('telefone')->update(['telefone' => $organization->telefone_contato]);
            }
        });
        DB::table('staff_whatsapp_deliveries')->where('status', 'pending')
            ->where('event_key', 'like', 'staff:%:workout_plan_expired:%')
            ->update(['status' => 'cancelled', 'error' => 'Avisos de ficha por WhatsApp desativados.', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('organizacao_usuario', fn (Blueprint $table) => $table->dropColumn('telefone'));
    }
};
