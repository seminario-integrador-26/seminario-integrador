<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La Nota (grupo Internos), como la Apertura y el Cierre de turno, se notifica
 * (Telegram o WhatsApp) a todos los grupos destinatarios: se asocia a los
 * grupos que ya existen. El Administrador de sistema puede quitarla desde la
 * matriz de Notificaciones.
 *
 * Solo inserta filas: no frena el servicio. En una base nueva el tipo todavía
 * no existe (lo crea TipoEventoSeeder) y no hace nada; los grupos nuevos la
 * traen marcada por defecto desde el formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipos = DB::table('tipos_evento')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('grupos_evento.codigo', 'internos')
            ->where('tipos_evento.nombre', 'Nota')
            ->pluck('tipos_evento.id');

        foreach (DB::table('grupos_interesados')->pluck('id') as $grupo) {
            foreach ($tipos as $tipo) {
                DB::table('grupo_interesado_tipo_evento')->insertOrIgnore([
                    'grupo_interesado_id' => $grupo,
                    'tipo_evento_id' => $tipo,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se revierte: no se sabe qué grupos ya tenían la Nota asociada
        // antes de esta migración.
    }
};
