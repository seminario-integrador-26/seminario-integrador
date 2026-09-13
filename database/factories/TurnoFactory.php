<?php

namespace Database\Factories;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supervisor_id' => User::factory(),
            'fecha' => now()->toDateString(),
            'hora_inicio' => '06:00:00',
            'hora_fin' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => ['hora_fin' => '14:00:00']);
    }
}
