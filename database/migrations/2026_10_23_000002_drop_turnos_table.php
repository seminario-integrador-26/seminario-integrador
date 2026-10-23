<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rediseño del Libro de guardia (docs/rediseno-libro-guardia.md): la tabla
 * turnos desaparece. Abrir y cerrar el turno pasa a ser registrar un evento
 * interno (Apertura de turno / Cierre de turno) y se puede registrar sin una
 * apertura vigente.
 *
 * Decisión del equipo: los turnos cargados se DESCARTAN; los eventos se
 * conservan y solo pierden turno_id. deploy.sh hace backup antes de migrar.
 *
 * La vista eventos_vigentes incluye e.turno_id: se recrea sin esa columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::table('eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
        });

        Schema::dropIfExists('turnos');

        $this->crearVista(conTurno: false);
    }

    public function down(): void
    {
        DB::statement('drop view if exists eventos_vigentes');

        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->json('personal_presente')->nullable();
            $table->time('hora_fin')->nullable()->index();
            $table->date('fecha_fin')->nullable();
            $table->text('novedades_pendientes')->nullable();
            $table->timestamps();
        });

        // Los turnos descartados no se recuperan: los eventos quedan sin turno.
        Schema::table('eventos', function (Blueprint $table) {
            $table->foreignId('turno_id')->nullable()->after('punto_monitoreo_id')
                ->constrained('turnos')->restrictOnDelete();
        });

        $this->crearVista(conTurno: true);
    }

    /**
     * Misma vista que 2026_10_16_000003_create_eventos_vigentes_view, con o
     * sin la columna turno_id.
     */
    private function crearVista(bool $conTurno): void
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

        $turno = $conTurno ? "e.turno_id,\n" : '';

        DB::statement(<<<SQL
            create view eventos_vigentes as
            select
                e.id,
                {$turno}
                e.usuario_id,
                e.created_at,
                {$campos}
            from eventos e
            SQL);
    }
};
