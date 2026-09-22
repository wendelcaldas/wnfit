<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('organizer');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->text('instructions')->nullable();
            $table->text('after_content')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('checkin_opens_at')->nullable();
            $table->dateTime('checkin_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
        });
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('access_hash', 64)->unique();
            $table->string('recovery_hash', 64)->unique();
            $table->dateTime('privacy_accepted_at');
            $table->boolean('marketing_opt_in')->default(false);
            $table->dateTime('checked_in_at')->nullable();
            $table->string('attendance_source')->nullable();
            $table->foreignId('attendance_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
