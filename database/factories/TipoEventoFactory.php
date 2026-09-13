<?php

namespace Database\Factories;

use App\Models\TipoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEvento>
 */
class TipoEventoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(3, true),
            'categoria' => fake()->randomElement(['prevencion', 'convivencia_urbana', 'seguridad_publica']),
            'requiere_ubicacion' => true,
            'es_cuantificable' => true,
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn () => ['requiere_ubicacion' => false]);
    }
}
