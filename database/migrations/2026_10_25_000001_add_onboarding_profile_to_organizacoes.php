<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable foreign keys outside a transaction before rebuilding the table.
    public $withinTransaction = false;

    public function up(): void
    {
        $this->alterOrganizations(function (Blueprint $table) {
            $table->string('tipo', 30)->nullable()->change();
            $table->json('onboarding_profile')->nullable();
        });
    }

    public function down(): void
    {
        // Keep tipo nullable: new registrations do not infer a business category.
        $this->alterOrganizations(function (Blueprint $table) {
            $table->dropColumn('onboarding_profile');
        });
    }

    private function alterOrganizations(Closure $callback): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => Schema::table('organizacoes', $callback));

            return;
        }

        Schema::table('organizacoes', $callback);
    }
};
