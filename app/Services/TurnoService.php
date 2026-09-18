<?php

namespace App\Services;

use App\Exceptions\TurnoYaAbiertoException;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * US-025: apertura, cierre y consulta de los turnos de guardia.
 *
 * SINGLE RESPONSIBILITY: sólo turnos. La auditoría la escribe el controller vía
 * AuditoriaAccesoService; el registro de eventos vive en EventoService.
 *
 * REGLA DE NEGOCIO: un turno activo por Supervisor a la vez (se garantiza en
 * abrir() con lockForUpdate dentro de la transacción).
 */
class TurnoService
{
    /**
     * Turno de guardia abierto del supervisor (o null), con su supervisor cargado.
     */
    public function activoDe(User $supervisor): ?Turno
    {
        return Turno::activoDe($supervisor->id)?->load('supervisor:id,name');
    }

    /**
     * Abre un turno para el supervisor con fecha/hora actuales y el personal presente.
     *
     * @param  array{personal_presente: array<int, string>}  $datos
     *
     * @throws TurnoYaAbiertoException
     */
    public function abrir(User $supervisor, array $datos): Turno
    {
        return DB::transaction(function () use ($supervisor, $datos) {
            // Bloquea las filas del supervisor para evitar dos aperturas en paralelo.
            $abierto = Turno::abierto()
                ->deSupervisor($supervisor->id)
                ->lockForUpdate()
                ->first();

            if ($abierto !== null) {
                throw new TurnoYaAbiertoException;
            }

            $ahora = Carbon::now();

            return Turno::create([
                'supervisor_id' => $supervisor->id,
                'fecha' => $ahora->toDateString(),
                'hora_inicio' => $ahora->format('H:i:s'),
                'personal_presente' => array_values($datos['personal_presente']),
                'hora_fin' => null,
            ]);
        });
    }

    /**
     * Cierra el turno registrando fecha/hora de fin y las novedades pendientes.
     *
     * @param  array{novedades_pendientes?: string|null}  $datos
     */
    public function cerrar(Turno $turno, array $datos): Turno
    {
        $ahora = Carbon::now();

        $turno->update([
            'fecha_fin' => $ahora->toDateString(),
            'hora_fin' => $ahora->format('H:i:s'),
            'novedades_pendientes' => $datos['novedades_pendientes'] ?? null,
        ]);

        return $turno;
    }

    /**
     * Historial de turnos, del más reciente al más antiguo, con el supervisor y
     * la cantidad de eventos de cada turno.
     *
     * @return LengthAwarePaginator<int, Turno>
     */
    public function historial(int $porPagina = 20): LengthAwarePaginator
    {
        return Turno::query()
            ->with('supervisor:id,name')
            ->withCount('eventos')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
