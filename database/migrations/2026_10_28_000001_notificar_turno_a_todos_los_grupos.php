<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Apertura y Cierre de turno se notifican (Telegram o WhatsApp) a todos los
 * grupos destinatarios: se asocian a los grupos que ya existen. El
 * Administrador de sistema puede quitarlos desde la matriz de Notificaciones.
 *
 * Solo inserta filas: no frena el servicio. En una base nueva los tipos
 * todavía no existen (los crea TipoEventoSeeder) y no hace nada; los grupos
 * nuevos los traen marcados por defecto desde el formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipos = DB::table('tipos_evento')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('grupos_evento.codigo', 'internos')
            ->whereIn('tipos_evento.nombre', ['Apertura de turno', 'Cierre de turno'])
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
        // No se revierte: no se sabe qué grupos ya tenían el turno asociado
        // antes de esta migración.
    }
};
