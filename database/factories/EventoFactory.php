<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evento>
 */
class EventoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tipo_evento_id' => TipoEvento::factory()->sinUbicacion(),
            'punto_monitoreo_id' => null,
            'usuario_id' => User::factory(),
            'fecha_hora' => now()->subHour(),
            'timestamp_video' => null,
            'descripcion' => fake()->sentence(),
        ];
    }

    /**
     * Apertura de turno (requiere TipoEventoSeeder): sin ella no se registran
     * eventos.
     */
    public function aperturaDeTurno(): static
    {
        return $this->state(fn () => [
            'tipo_evento_id' => TipoEvento::interno(TipoEvento::APERTURA_TURNO),
            'franja' => 'manana',
            'grupo_guardia' => 1,
        ]);
    }
}
