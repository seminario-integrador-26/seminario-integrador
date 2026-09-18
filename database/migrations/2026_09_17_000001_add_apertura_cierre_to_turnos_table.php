<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-025: apertura y cierre formal del turno de guardia.
 *
 * La tabla `turnos` ya estaba creada (create_turnos_table) con supervisor_id,
 * fecha, hora_inicio y hora_fin. Acá se agregan los datos que exige la US:
 *
 * - personal_presente     -> lista de nombres del personal en la guardia.
 * - novedades_pendientes  -> se completa al cerrar el turno.
 * - fecha_fin             -> día del cierre; complementa hora_fin para las
 *                            guardias que cruzan la medianoche (hora_fin sola
 *                            perdería el día).
 *
 * Nullable en DB para no romper filas ya existentes; la obligatoriedad de
 * personal_presente se valida en AbrirTurnoRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            if (! Schema::hasColumn('turnos', 'personal_presente')) {
                $table->json('personal_presente')->nullable()->after('hora_inicio');
            }

            if (! Schema::hasColumn('turnos', 'fecha_fin')) {
                $table->date('fecha_fin')->nullable()->after('hora_fin');
            }

            if (! Schema::hasColumn('turnos', 'novedades_pendientes')) {
                $table->text('novedades_pendientes')->nullable()->after('fecha_fin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn(['personal_presente', 'fecha_fin', 'novedades_pendientes']);
        });
    }
};
