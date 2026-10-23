<?php

namespace Database\Seeders;

use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use App\Services\ErrataService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SOLO DESARROLLO / DEMO: turnos cerrados y eventos de prueba de los últimos
 * 12 meses, para probar estadísticas, análisis, mapa, heatmap, Excel, Libro de
 * guardia en PDF y API con datos. No está en DatabaseSeeder ni en deploy.sh.
 *
 * Uso (después de `php artisan migrate:fresh --seed`):
 *   php artisan db:seed --class=EventoDemoSeeder
 *
 * - 3 turnos por día (06–14, 14–22, 22–06 que cruza la medianoche), cada uno
 *   con su evento Apertura de turno y Cierre de turno; el turno de hoy lo
 *   abre el Supervisor registrando la apertura.
 * - Más eventos de tarde/noche y en un puñado de PMs "calientes", para que el
 *   ranking, la franja horaria y el heatmap muestren algo.
 * - Los eventos se crean sin pasar por EventoService: no disparan
 *   EventoRegistrado, así que no se envían notificaciones.
 * - Algunos eventos llevan fe de errata (vía ErrataService), para ver los
 *   valores vigentes y el historial.
 */
class EventoDemoSeeder extends Seeder
{
    public const DIAS = 365;

    /** [hora_inicio, hora_fin, peso de eventos del turno] */
    private const TURNOS = [
        ['06:00:00', '14:00:00', 0.8],
        ['14:00:00', '22:00:00', 1.4],
        ['22:00:00', '06:00:00', 1.1],
    ];

    private const DESCRIPCIONES = [
        'Se observa la situación por cámara y se da aviso.',
        'Se sigue el hecho por cámara hasta que sale de cuadro.',
        'Se informa a la guardia y se realiza seguimiento visual.',
        'Detectado en recorrida de cámaras.',
        'Aviso recibido por llamada; se verifica por cámara.',
        'Se constata el hecho; sin novedades posteriores.',
        'Se deja constancia para su registro.',
    ];

    public function run(ErrataService $erratas): void
    {
        if (app()->isProduction()) {
            $this->command?->error('EventoDemoSeeder es solo para desarrollo: no se corre en producción.');

            return;
        }

        if (Evento::query()->exists()) {
            $this->command?->warn('Ya hay eventos cargados: no se siembran los de prueba. Usá `php artisan migrate:fresh --seed` y volvé a correr este seeder.');

            return;
        }

        $supervisores = User::role('Supervisor')->get();
        $apertura = TipoEvento::interno(TipoEvento::APERTURA_TURNO);
        $cierre = TipoEvento::interno(TipoEvento::CIERRE_TURNO);
        // Al azar, solo los tipos que se cargan a mano durante la guardia.
        $tipos = TipoEvento::whereDoesntHave('grupoEvento', fn ($q) => $q->where('codigo', GrupoEvento::INTERNOS))
            ->orWhereNotIn('nombre', TipoEventoSeeder::INTERNOS_NO_CUANTIFICABLES)
            ->get();
        $puntos = PuntoMonitoreo::pluck('id');

        if ($supervisores->isEmpty() || $tipos->isEmpty() || $puntos->isEmpty() || ! $apertura || ! $cierre) {
            $this->command?->error('Faltan supervisores, tipos de evento o PMs: corré primero `php artisan db:seed`.');

            return;
        }

        // ~25 PMs concentran la mitad de los eventos con ubicación.
        $calientes = $puntos->shuffle()->take(25)->values();

        DB::transaction(function () use ($supervisores, $tipos, $puntos, $calientes, $erratas, $apertura, $cierre) {
            $total = 0;
            $turnos = 0;
            $hoy = Carbon::today();

            for ($d = self::DIAS; $d >= 1; $d--) {
                $dia = $hoy->copy()->subDays($d);
                // Más movimiento los fines de semana.
                $factorDia = $dia->isWeekend() ? 1.3 : 1.0;

                foreach (self::TURNOS as $i => [$inicio, $fin, $peso]) {
                    $cruza = $fin < $inicio;
                    if ($cruza && $d === 1) {
                        continue; // terminaría hoy: el turno de hoy lo abre el Supervisor.
                    }
                    $supervisor = $supervisores[($d * 3 + $i) % $supervisores->count()];

                    $desde = Carbon::parse("{$dia->toDateString()} {$inicio}");
                    $hasta = Carbon::parse(($cruza ? $dia->copy()->addDay() : $dia)->toDateString()." {$fin}");

                    $this->interno($apertura, $supervisor, $desde, 'Personal presente: Operador '
                        .(($d + $i) % 6 + 1).', Operador '.(($d + $i + 3) % 6 + 1).'.');
                    $turnos++;

                    $cantidad = $this->poisson(3 * $peso * $factorDia);

                    for ($n = 0; $n < $cantidad; $n++) {
                        $evento = $this->crearEvento($supervisor, $tipos, $puntos, $calientes, $desde);
                        $total++;

                        if (mt_rand(1, 50) === 1) {
                            $this->corregir($erratas, $evento, $tipos, $supervisor);
                        }
                    }

                    $this->interno($cierre, $supervisor, $hasta->copy()->subSecond(), mt_rand(1, 10) === 1
                        ? 'Novedades pendientes: cámara con imagen intermitente; se avisó a mantenimiento.'
                        : 'Sin novedades pendientes.');
                }
            }

            $this->command?->info("Eventos de prueba: {$total} en {$turnos} turnos cerrados.");
        });
    }

    /**
     * Apertura o cierre de turno (evento interno, sin ubicación).
     */
    private function interno(int $tipoId, User $autor, Carbon $fechaHora, string $descripcion): void
    {
        Evento::create([
            'tipo_evento_id' => $tipoId,
            'usuario_id' => $autor->id,
            'fecha_hora' => $fechaHora,
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * @param  Collection<int, TipoEvento>  $tipos
     * @param  Collection<int, int>  $puntos
     * @param  Collection<int, int>  $calientes
     */
    private function crearEvento(User $autor, Collection $tipos, Collection $puntos, Collection $calientes, Carbon $desde): Evento
    {
        $tipo = $tipos->random();
        // Dentro de las 8 h del turno, sesgado hacia la segunda mitad.
        $minutos = (int) round(480 * sqrt(mt_rand() / mt_getrandmax()));
        $fechaHora = $desde->copy()->addMinutes(min($minutos, 479))->addSeconds(mt_rand(0, 59));

        $puntoId = null;
        if ($tipo->requiere_ubicacion) {
            $puntoId = mt_rand(0, 1) ? $calientes->random() : $puntos->random();
        }

        return Evento::create([
            'tipo_evento_id' => $tipo->id,
            'punto_monitoreo_id' => $puntoId,
            'usuario_id' => $autor->id,
            'fecha_hora' => $fechaHora,
            'timestamp_video' => $puntoId ? $fechaHora->format('H:i:s') : null,
            'descripcion' => self::DESCRIPCIONES[array_rand(self::DESCRIPCIONES)],
        ]);
    }

    /**
     * Fe de errata de ejemplo: corrige el tipo (dentro del mismo requisito de
     * ubicación) o la descripción.
     *
     * @param  Collection<int, TipoEvento>  $tipos
     */
    private function corregir(ErrataService $erratas, Evento $evento, Collection $tipos, User $autor): void
    {
        $evento->loadMissing('tipoEvento');
        $otroTipo = $tipos
            ->where('requiere_ubicacion', $evento->tipoEvento->requiere_ubicacion)
            ->where('id', '!=', $evento->tipo_evento_id)
            ->random();

        $cambiaTipo = mt_rand(0, 1) === 1;

        $erratas->registrar($evento, [
            'tipo_evento_id' => $cambiaTipo ? $otroTipo->id : $evento->tipo_evento_id,
            'fecha' => $evento->fecha_hora->toDateString(),
            'hora' => $evento->fecha_hora->format('H:i:s'),
            'punto_monitoreo_id' => $evento->punto_monitoreo_id,
            'timestamp_video' => $evento->timestamp_video,
            'descripcion' => $cambiaTipo ? $evento->descripcion : $evento->descripcion.' Se agrega: intervino personal policial.',
        ], $cambiaTipo ? 'Se cargó con el tipo equivocado.' : 'Faltaba un dato en la descripción.', $autor);
    }

    private function poisson(float $media): int
    {
        $limite = exp(-$media);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $limite);

        return $k - 1;
    }
}
