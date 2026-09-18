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
            'personal_presente' => [$this->faker->name(), $this->faker->name()],
            'hora_fin' => null,
            'fecha_fin' => null,
            'novedades_pendientes' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => [
            'hora_fin' => '14:00:00',
            'fecha_fin' => now()->toDateString(),
        ]);
    }
}
