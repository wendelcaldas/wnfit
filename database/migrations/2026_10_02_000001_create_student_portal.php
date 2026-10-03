<?php

use App\Models\Treino;
use App\Services\WorkoutPublishingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->unique()->constrained('alunos')->cascadeOnDelete();
            $table->string('username', 60)->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->unsignedInteger('credential_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('student_workout_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('treino_id')->nullable()->constrained('treinos')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->string('status', 20)->default('ativo');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['aluno_id', 'status']);
        });
        Schema::create('student_workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('student_workout_plan_id')->constrained('student_workout_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_index');
            $table->json('snapshot');
            $table->json('progress');
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['aluno_id', 'status']);
        });
        // Freeze existing assignments before any library edits; no credentials are generated for old students.
        DB::table('aluno_treino')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $workout = Treino::find($row->treino_id);
                if (! $workout) {
                    continue;
                }
                DB::table('student_workout_plans')->insert([
                    'aluno_id' => $row->aluno_id, 'treino_id' => $row->treino_id,
                    'snapshot' => json_encode(app(WorkoutPublishingService::class)->snapshot($workout)),
                    'status' => $row->ativo ? 'ativo' : 'encerrado',
                    'starts_on' => substr($row->created_at ?? now()->toDateTimeString(), 0, 10),
                    'created_at' => $row->created_at ?? now(), 'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_workout_sessions');
        Schema::dropIfExists('student_workout_plans');
        Schema::dropIfExists('student_accounts');
    }
};
