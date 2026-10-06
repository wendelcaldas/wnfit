<?php

namespace Database\Seeders;

use App\Models\Exercicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = require database_path('data/exercises.php');

        foreach ($exercises as [$name, $muscle, $secondary, $equipment, $category, $level, $instructions]) {
            $imagePath = '/media/exercises/v1/'.Str::slug($name).'.webp';
            $imageUrl = is_file(public_path(ltrim($imagePath, '/'))) ? $imagePath : null;
            $exercise = Exercicio::query()->firstOrCreate(
                ['organizacao_id' => null, 'nome' => $name],
                [
                    'grupo_muscular' => $muscle,
                    'grupo_secundario' => $secondary,
                    'equipamento' => $equipment,
                    'categoria' => $category,
                    'nivel' => $level,
                    'instrucoes' => $instructions,
                    'imagem_url' => $imageUrl,
                    'cuidados' => match ($category) {
                        'mobilidade' => 'Respeite a amplitude orientada pelo professor, sem forçar ou usar impulsos. Se sentir dor, interrompa e peça orientação.',
                        'cardio' => 'Siga o ritmo e o tempo definidos pelo professor e ajuste o equipamento antes de iniciar. Se sentir dor ou mal-estar, interrompa e peça orientação.',
                        default => 'Use a carga, a amplitude e os apoios definidos pelo professor. Se sentir dor, interrompa e peça orientação.',
                    },
                    'origem' => 'wnfit',
                    'ativo' => true,
                ],
            );
            // Rerunning a seed must not overwrite reviewed metadata or reactivate an exercise.
            $missing = [];
            if (blank($exercise->instrucoes)) {
                $missing['instrucoes'] = $instructions;
            }
            if (blank($exercise->imagem_url) && $imageUrl) {
                $missing['imagem_url'] = $imageUrl;
            }
            if ($missing) {
                $exercise->update($missing);
            }
        }
    }
}
