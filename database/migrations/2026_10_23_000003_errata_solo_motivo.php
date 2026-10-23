<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La fe de errata pasa a registrar solo el motivo: los datos del evento quedan
 * bloqueados y campo_corregido va en null. Las erratas anteriores (con campo y
 * valores) se conservan y siguen aplicando en la vista eventos_vigentes, que
 * se recrea igual (SQLite reconstruye la tabla al cambiar la columna y no lo
 * permite con la vista apuntándola).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('erratas', function (Blueprint $table) {
            $table->string('campo_corregido', 40)->nullable()->change();
        });

        $this->crearVista();
    }

    public function down(): void
    {
        // Sin vuelta atrás automática: las erratas de solo motivo no tienen campo.
    }

    /**
     * Misma vista que 2026_10_23_000002_drop_turnos_table (sin turno_id).
     */
    private function crearVista(): void
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

        DB::statement(<<<SQL
            create view eventos_vigentes as
            select
                e.id,
                e.usuario_id,
                e.created_at,
                {$campos}
            from eventos e
            SQL);
    }
};
