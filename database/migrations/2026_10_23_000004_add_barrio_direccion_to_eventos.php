<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evento sin punto de monitoreo: se puede cargar barrio y dirección (si el
 * tipo exige ubicación y no hay PM, son obligatorios). La vista
 * eventos_vigentes se recrea con las dos columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('eventos', function (Blueprint $table) {
            $table->foreignId('barrio_id')->nullable()->after('punto_monitoreo_id')->constrained('barrios');
            $table->string('direccion', 255)->nullable()->after('barrio_id');
        });

        $this->crearVista(conDireccion: true);
    }

    public function down(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barrio_id');
            $table->dropColumn('direccion');
        });

        $this->crearVista(conDireccion: false);
    }

    /**
     * Misma vista que 2026_10_23_000003_errata_solo_motivo, más barrio_id y
     * direccion (no se corrigen por errata: salen directo del evento).
     */
    private function crearVista(bool $conDireccion): void
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

        $direccion = $conDireccion ? "e.barrio_id,\n                e.direccion," : '';

        DB::statement(<<<SQL
            create view eventos_vigentes as
            select
                e.id,
                e.usuario_id,
                e.created_at,
                {$direccion}
                {$campos}
            from eventos e
            SQL);
    }
};
