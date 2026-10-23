<?php

namespace Database\Factories;

use App\Models\GrupoEvento;
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
            // Internos (libro de guardia) solo con delGrupo('internos').
            'grupo_evento_id' => fn () => GrupoEvento::where('codigo', '!=', GrupoEvento::INTERNOS)->inRandomOrder()->value('id'),
            'nombre' => fake()->unique()->words(3, true),
            'requiere_ubicacion' => true,
            'es_cuantificable' => true,
            'notifica' => false,
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn () => ['requiere_ubicacion' => false]);
    }

    /** Asigna el grupo por su código (los grupos los crea la migración). */
    public function delGrupo(string $codigo): static
    {
        return $this->state(fn () => ['grupo_evento_id' => GrupoEvento::where('codigo', $codigo)->value('id')]);
    }
}
