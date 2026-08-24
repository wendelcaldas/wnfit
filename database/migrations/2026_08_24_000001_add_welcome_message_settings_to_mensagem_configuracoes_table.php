<?php

use App\Services\Messaging\MessagingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagem_configuracoes', function (Blueprint $table) {
            $table->string('welcome_template_key', 80)->default('student_welcome_default')->after('charge_message_body');
            $table->string('welcome_template_sid')->nullable()->after('welcome_template_key');
            $table->text('welcome_message_body')->nullable()->after('welcome_template_sid');
        });

        DB::table('mensagem_configuracoes')
            ->whereNull('welcome_message_body')
            ->update(['welcome_message_body' => MessagingService::defaultWelcomeMessageBody()]);
    }

    public function down(): void
    {
        Schema::table('mensagem_configuracoes', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_template_key',
                'welcome_template_sid',
                'welcome_message_body',
            ]);
        });
    }
};
