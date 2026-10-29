<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_schedule_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizacao_id')->unique()->constrained('organizacoes')->cascadeOnDelete();
            $t->boolean('enforced')->default(false);
            $t->json('weekly_hours');
            $t->boolean('public_enabled')->default(false);
            $t->unsignedSmallInteger('duration_minutes')->default(60);
            $t->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 120)->default('Atendimento individual');
            $t->string('location', 120)->nullable();
            $t->string('public_token', 64)->nullable()->unique();
            $t->timestamps();
        });
        Schema::create('public_schedule_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $t->foreignId('agendamento_id')->unique()->constrained('agendamentos')->cascadeOnDelete();
            $t->uuid('request_id');
            $t->string('reference', 24)->unique();
            $t->string('name', 120);
            $t->string('phone', 30);
            $t->string('email')->nullable();
            $t->timestamps();
            $t->unique(['organizacao_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_schedule_bookings');
        Schema::dropIfExists('organization_schedule_settings');
    }
};
