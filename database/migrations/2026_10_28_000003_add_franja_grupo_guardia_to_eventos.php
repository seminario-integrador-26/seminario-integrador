<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Al abrir el turno el Supervisor indica su franja (mañana, tarde o noche) y
 * su grupo de guardia (1 a 4). Solo la Apertura de turno los lleva. La vista
 * eventos_vigentes se recrea con las dos columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('eventos', function (Blueprint $table) {
            $table->string('franja', 10)->nullable()->after('direccion');
            $table->unsignedTinyInteger('grupo_guardia')->nullable()->after('franja');
        });

        $this->crearVista(conGuardia: true);
    }

    public function down(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['franja', 'grupo_guardia']);
        });

        $this->crearVista(conGuardia: false);
    }

    /**
     * Misma vista que 2026_10_23_000004_add_barrio_direccion_to_eventos, más
     * franja y grupo_guardia (no se corrigen por errata: salen directo del evento).
     */
    private function crearVista(bool $conGuardia): void
    {
        $tipos = DB::getDriverName() === 'pgsql'
            ? [
                'tipo_evento_id' => 'bigint',
                'punto_monitoreo_id' => 'bigint',
                'fecha_hora' => 'timestamp(0) without time zone',
                'timestamp_video' => 'time(0) without time zone',
                'descripcion' => 'text',
            ]
            // SQLite (tests): fechas y horas se guardan como texto.
            : [
                'tipo_evento_id' => 'integer',
                'punto_monitoreo_id' => 'integer',
                'fecha_hora' => 'text',
                'timestamp_video' => 'text',
                'descripcion' => 'text',
            ];

        $campos = collect($tipos)->map(function (string $tipo, string $campo) {
            $ultima = "from erratas r where r.evento_id = e.id and r.campo_corregido = '{$campo}'";

            return "case when exists (select 1 {$ultima}) "
                ."then cast((select r.valor_nuevo {$ultima} order by r.id desc limit 1) as {$tipo}) "
                ."else e.{$campo} end as {$campo}";
        })->implode(",\n    ");

        $guardia = $conGuardia ? "e.franja,\n                e.grupo_guardia," : '';

        DB::statement(<<<SQL
            create view eventos_vigentes as
            select
                e.id,
                e.usuario_id,
                e.created_at,
                e.barrio_id,
                e.direccion,
                {$guardia}
                {$campos}
            from eventos e
            SQL);
    }
};
