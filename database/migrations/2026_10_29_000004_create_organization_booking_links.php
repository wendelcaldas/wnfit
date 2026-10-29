<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_booking_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $t->string('title', 120);
            $t->unsignedSmallInteger('duration_minutes');
            $t->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('location', 120)->nullable();
            $t->boolean('active')->default(false);
            $t->string('public_token', 64)->unique();
            $t->timestamps();
        });
        DB::table('organization_schedule_settings')->whereNotNull('public_token')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $s) {
                DB::table('organization_booking_links')->insert(['organizacao_id' => $s->organizacao_id, 'title' => $s->title, 'duration_minutes' => $s->duration_minutes, 'instructor_id' => $s->instructor_id, 'location' => $s->location, 'active' => $s->public_enabled, 'public_token' => $s->public_token, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::table('public_schedule_bookings', function (Blueprint $t) {
            $t->string('booking_token', 64)->nullable();
            $t->foreignId('booking_link_id')->nullable()->constrained('organization_booking_links')->nullOnDelete();
        });
        DB::table('public_schedule_bookings')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $token = DB::table('organization_schedule_settings')->where('organizacao_id', $row->organizacao_id)->value('public_token');
                DB::table('public_schedule_bookings')->where('id', $row->id)->update(['booking_token' => $token, 'booking_link_id' => DB::table('organization_booking_links')->where('public_token', $token)->value('id')]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('public_schedule_bookings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('booking_link_id');
            $t->dropColumn('booking_token');
        });
        Schema::dropIfExists('organization_booking_links');
    }
};
