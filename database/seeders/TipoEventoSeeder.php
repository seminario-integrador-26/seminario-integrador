<?php

namespace Database\Seeders;

use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Tipos de evento de EJEMPLO para desarrollo.
 *
 * // TODO: confirmar con el equipo el catálogo real de tipos del Centro de Monitoreo.
 */
class TipoEventoSeeder extends Seeder
{
    /**
     * [nombre, categoria, requiere_ubicacion, es_cuantificable]
     *
     * @var list<array{0: string, 1: string, 2: bool, 3: bool}>
     */
    private const TIPOS = [
        ['Persona en actitud sospechosa', 'prevencion', true, true],
        ['Ruidos molestos', 'convivencia_urbana', true, true],
        ['Disturbios en vía pública', 'convivencia_urbana', true, true],
        ['Robo / hurto', 'seguridad_publica', true, true],
        ['Accidente de tránsito', 'seguridad_publica', true, true],
        ['Falla de cámara', 'informativo', true, false],
        ['Novedad de guardia', 'informativo', false, false],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as [$nombre, $categoria, $requiereUbicacion, $esCuantificable]) {
            TipoEvento::updateOrCreate(['nombre' => $nombre], [
                'categoria' => $categoria,
                'requiere_ubicacion' => $requiereUbicacion,
                'es_cuantificable' => $esCuantificable,
            ]);
        }
    }
}
