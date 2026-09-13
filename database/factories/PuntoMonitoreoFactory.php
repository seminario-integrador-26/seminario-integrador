<?php

namespace Database\Factories;

use App\Models\PuntoMonitoreo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuntoMonitoreo>
 */
class PuntoMonitoreoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PM-####'),
            'nombre' => fake()->streetName().' y '.fake()->streetName(),
            'jurisdiccion' => fake()->randomElement(array_keys(PuntoMonitoreo::JURISDICCIONES)),
            // Área aproximada de Villa María.
            'latitud' => fake()->latitude(-32.44, -32.38),
            'longitud' => fake()->longitude(-63.28, -63.20),
        ];
    }
}
