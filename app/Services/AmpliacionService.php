<?php

namespace App\Services;

use App\Models\Ampliacion;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ampliación de una novedad: suma información posterior sin tocar el evento
 * (inmutable, regla de negocio 1). Mismo circuito que la fe de errata.
 *
 * SINGLE RESPONSIBILITY: crear registros de ampliación.
 */
class AmpliacionService
{
    public function __construct(
        private readonly EventoService $eventos,
        private readonly LibroGuardiaService $libro,
    ) {}

    /**
     * Registra la ampliación y, en la misma transacción, la constancia
     * (evento interno Ampliación) que la deja en el libro de guardia y se
     * notifica según la matriz.
     *
     * @throws DomainException si el evento es una constancia o no hay un
     *                         turno abierto (la constancia debe caer en uno).
     */
    public function ampliar(Evento $evento, string $detalle, User $autor): Ampliacion
    {
        if ($this->eventos->esConstancia($evento)) {
            throw new DomainException('Las constancias las registra el sistema: no se amplían.');
        }

        if (! $this->libro->aperturaVigente()) {
            throw new DomainException('No hay un turno abierto: registrá primero la Apertura de turno.');
        }

        return DB::transaction(function () use ($evento, $detalle, $autor) {
            $ampliacion = Ampliacion::create([
                'evento_id' => $evento->id,
                'usuario_id' => $autor->id,
                'detalle' => $detalle,
            ]);
            $this->eventos->registrarConstancia(TipoEvento::AMPLIACION, $this->constancia($evento, $detalle), $autor);

            return $ampliacion;
        });
    }

    /**
     * "Ampliación sobre AT-2026-0012: la persona fue aprehendida en Bv. España y Santa Fe."
     */
    public function constancia(Evento $evento, string $detalle): string
    {
        return "Ampliación sobre {$evento->codigo()}: {$detalle}";
    }
}
