<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\Turno;
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
            'turno_id' => Turno::factory(),
            'usuario_id' => User::factory(),
            'fecha_hora' => now()->subHour(),
            'timestamp_video' => null,
            'descripcion' => fake()->sentence(),
        ];
    }
}
