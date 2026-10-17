<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_registration_id')->constrained()->restrictOnDelete();
            $t->string('disk');
            $t->string('path');
            $t->string('thumbnail_path');
            $t->string('status', 20)->default('pending');
            $t->timestamp('image_consent_at');
            $t->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('moderated_at')->nullable();
            $t->timestamps();
            $t->index(['event_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
    }
};
