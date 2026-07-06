<?php

namespace Database\Seeders;

use App\Models\Exercicio;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            ['Agachamento livre', 'Quadriceps', 'Gluteos', 'Barra'], ['Leg press 45', 'Quadriceps', 'Gluteos', 'Maquina'],
            ['Cadeira extensora', 'Quadriceps', null, 'Maquina'], ['Mesa flexora', 'Posteriores de coxa', null, 'Maquina'],
            ['Levantamento terra romeno', 'Posteriores de coxa', 'Gluteos', 'Barra'], ['Elevacao pelvica', 'Gluteos', 'Posteriores de coxa', 'Barra'],
            ['Panturrilha em pe', 'Panturrilhas', null, 'Maquina'], ['Supino reto', 'Peitoral', 'Triceps', 'Barra'],
            ['Supino inclinado com halteres', 'Peitoral', 'Ombros', 'Halteres'], ['Crucifixo com halteres', 'Peitoral', null, 'Halteres'],
            ['Remada curvada', 'Costas', 'Biceps', 'Barra'], ['Puxada frontal', 'Costas', 'Biceps', 'Polia'],
            ['Remada baixa', 'Costas', 'Biceps', 'Polia'], ['Desenvolvimento com halteres', 'Ombros', 'Triceps', 'Halteres'],
            ['Elevacao lateral', 'Ombros', null, 'Halteres'], ['Rosca direta', 'Biceps', null, 'Barra'],
            ['Rosca martelo', 'Biceps', 'Antebracos', 'Halteres'], ['Triceps na polia', 'Triceps', null, 'Polia'],
            ['Triceps frances', 'Triceps', null, 'Halteres'], ['Prancha abdominal', 'Core', null, 'Peso corporal'],
            ['Abdominal supra', 'Core', null, 'Peso corporal'], ['Flexao de bracos', 'Peitoral', 'Triceps', 'Peso corporal'],
            ['Afundo', 'Quadriceps', 'Gluteos', 'Peso corporal'], ['Burpee', 'Corpo inteiro', null, 'Peso corporal'],
        ];

        foreach ($exercises as [$name, $muscle, $secondary, $equipment]) {
            Exercicio::query()->firstOrCreate(
                ['organizacao_id' => null, 'nome' => $name],
                ['grupo_muscular' => $muscle, 'grupo_secundario' => $secondary, 'equipamento' => $equipment, 'categoria' => 'musculacao', 'nivel' => 'todos', 'origem' => 'wnfit'],
            );
        }
    }
}
