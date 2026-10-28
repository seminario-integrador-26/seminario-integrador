<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\TipoEvento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Libro de guardia sin tabla de turnos (docs/rediseno-libro-guardia.md): el
 * turno se abre y se cierra registrando un evento interno (Apertura de turno /
 * Cierre de turno). Una guardia es el tramo del libro que va de una Apertura
 * al Cierre siguiente; si todavía no hay Cierre, está en curso.
 *
 * Lee los valores vigentes (con fe de errata): una apertura cargada con la
 * hora equivocada se corrige por errata y el tramo se recalcula.
 */
class LibroGuardiaService
{
    /**
     * Última Apertura de turno sin un Cierre posterior, o null.
     */
    public function aperturaVigente(): ?Evento
    {
        $apertura = $this->delTipo(TipoEvento::APERTURA_TURNO)
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->first();

        return $apertura && ! $this->cierreSiguiente($apertura) ? $apertura : null;
    }

    /**
     * Primer Cierre de turno a partir de la apertura, si es anterior a la
     * Apertura siguiente (un turno que nunca se cerró no se come al próximo).
     */
    public function cierreSiguiente(Evento $apertura): ?Evento
    {
        $cierre = $this->posterior(TipoEvento::CIERRE_TURNO, $apertura);
        $proxima = $this->posterior(TipoEvento::APERTURA_TURNO, $apertura);

        if (! $cierre || ($proxima && $this->antes($proxima, $cierre))) {
            return null;
        }

        return $cierre;
    }

    /**
     * Eventos de la guardia, de la apertura al cierre (ambos incluidos) o, si
     * sigue en curso, hasta ahora.
     *
     * @return Builder<Evento>
     */
    public function eventosDe(Evento $apertura, ?Evento $cierre = null): Builder
    {
        return Evento::vigentes()
            ->where('fecha_hora', '>=', $apertura->fecha_hora)
            ->when($cierre, fn (Builder $q) => $q->where('fecha_hora', '<=', $cierre->fecha_hora));
    }

    /**
     * US-012: aperturas para elegir qué guardia imprimir, de la más reciente a
     * la más antigua, con su cierre y la cantidad de eventos.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function aperturas(?string $fecha, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->delTipo(TipoEvento::APERTURA_TURNO)
            ->with('usuario:id,name')
            ->when($fecha, fn (Builder $q) => $q->whereDate('fecha_hora', $fecha))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString()
            ->through(function (Evento $apertura) {
                $cierre = $this->cierreSiguiente($apertura);

                return [
                    'id' => $apertura->id,
                    'fecha' => $apertura->fecha_hora->format('d/m/Y'),
                    'inicio' => $apertura->fecha_hora->format('H:i'),
                    'fin' => $cierre?->fecha_hora->format('H:i'),
                    'fecha_fin' => $cierre?->fecha_hora->format('d/m/Y'),
                    'supervisor' => $apertura->usuario?->name,
                    'guardia' => $apertura->guardia(),
                    'eventos' => $cierre ? $this->eventosDe($apertura, $cierre)->count() : null,
                ];
            });
    }

    /**
     * @return Builder<Evento>
     */
    private function delTipo(string $nombre): Builder
    {
        return Evento::vigentes()->where('tipo_evento_id', TipoEvento::interno($nombre));
    }

    private function posterior(string $nombre, Evento $apertura): ?Evento
    {
        return $this->delTipo($nombre)
            ->where(fn (Builder $q) => $q
                ->where('fecha_hora', '>', $apertura->fecha_hora)
                ->orWhere(fn (Builder $q) => $q
                    ->where('fecha_hora', $apertura->fecha_hora)
                    ->where('id', '>', $apertura->id)))
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->first();
    }

    private function antes(Evento $a, Evento $b): bool
    {
        return $a->fecha_hora < $b->fecha_hora
            || ($a->fecha_hora == $b->fecha_hora && $a->id < $b->id);
    }
}
