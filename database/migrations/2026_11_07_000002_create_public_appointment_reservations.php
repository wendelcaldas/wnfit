<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_appointment_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('reference', 24)->unique();
            $table->string('name', 120);
            $table->string('phone', 11);
            $table->timestamps();
            $table->unique(['agendamento_id', 'request_id'], 'appointment_reservation_request');
            $table->unique(['agendamento_id', 'phone'], 'appointment_reservation_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_appointment_reservations');
    }
};
