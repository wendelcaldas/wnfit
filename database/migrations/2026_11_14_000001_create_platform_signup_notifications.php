<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_signup_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->unique()->constrained('organizacoes')->cascadeOnDelete();
            $table->string('recipient', 20);
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_signup_notifications');
    }
};
