<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rediseño del Libro de guardia (docs/rediseno-libro-guardia.md): los grupos
 * pasan a ser Policía, Guardia Local e Internos.
 *
 * Se renombran las filas en lugar de crear grupos nuevos, así los tipos de
 * Seguridad Ciudadana quedan en Guardia Local y los de Oficios Recepcionados
 * en Internos sin tocar tipos_evento ni eventos. Los tipos internos nuevos
 * (Apertura de turno, Cierre de turno, Nota, Errata, Otros) los crea
 * TipoEventoSeeder, que deploy.sh corre después de migrar.
 */
return new class extends Migration
{
    /** codigo viejo => [codigo nuevo, nombre nuevo] */
    private const RENOMBRES = [
        'seguridad_ciudadana' => ['guardia_local', 'Guardia Local'],
        'oficios_recepcionados' => ['internos', 'Internos'],
    ];

    /** codigo nuevo => nombre viejo, para revertir. */
    private const NOMBRES_VIEJOS = [
        'guardia_local' => 'Seguridad Ciudadana',
        'internos' => 'Oficios Recepcionados',
    ];

    public function up(): void
    {
        foreach (self::RENOMBRES as $viejo => [$codigo, $nombre]) {
            DB::table('grupos_evento')->where('codigo', $viejo)
                ->update(['codigo' => $codigo, 'nombre' => $nombre, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Los tipos internos que haya sembrado TipoEventoSeeder quedan en el
        // grupo revertido (Oficios Recepcionados).
        foreach (self::RENOMBRES as $viejo => [$codigo]) {
            DB::table('grupos_evento')->where('codigo', $codigo)
                ->update(['codigo' => $viejo, 'nombre' => self::NOMBRES_VIEJOS[$codigo], 'updated_at' => now()]);
        }
    }
};
