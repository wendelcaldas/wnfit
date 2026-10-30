<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->foreignId('staff_notification_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100)->unique();
            $table->string('recipient', 20);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_whatsapp_deliveries');
    }
};
