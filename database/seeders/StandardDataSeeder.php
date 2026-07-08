<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StandardDataSeeder extends Seeder
{
    /**
     * Dados globais necessários em todos os ambientes, inclusive produção.
     */
    public function run(): void
    {
        $this->call([
            ExerciseSeeder::class,
        ]);
    }
}
