<?php

namespace App\Services;

use App\Models\Errata;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Único mecanismo de corrección permitido sobre un Evento (regla de negocio 1).
 *
 * SINGLE RESPONSIBILITY: crear registros de "fe de errata".
 * REGLA DE NEGOCIO: el Evento original NUNCA se sobreescribe y sus datos
 * quedan bloqueados: la errata es un registro separado que referencia al
 * evento y guarda el motivo y quién la hizo.
 */
class ErrataService
{
    public function __construct(
        private readonly EventoService $eventos,
        private readonly LibroGuardiaService $libro,
    ) {}

    /**
     * Fe de errata desde la UI: registra la errata y, en la misma
     * transacción, la constancia (evento interno Errata) que la deja en el
     * libro de guardia y se notifica según la matriz.
     *
     * @throws DomainException si el evento es una constancia o no hay un
     *                         turno abierto (la constancia debe caer en uno).
     */
    public function corregir(Evento $evento, string $motivo, User $autor): Errata
    {
        if ($this->eventos->esConstancia($evento)) {
            throw new DomainException('Las constancias de errata las registra el sistema: no se corrigen.');
        }

        if (! $this->libro->aperturaVigente()) {
            throw new DomainException('No hay un turno abierto: registrá primero la Apertura de turno.');
        }

        return DB::transaction(function () use ($evento, $motivo, $autor) {
            $errata = Errata::create([
                'evento_id' => $evento->id,
                'usuario_id' => $autor->id,
                'motivo' => $motivo,
            ]);
            $this->eventos->registrarConstancia(TipoEvento::ERRATA, $this->constancia($evento, $motivo), $autor);

            return $errata;
        });
    }

    /**
     * "Errata sobre AT-2026-0012: se cargó el PM equivocado; fue en la esquina de enfrente."
     */
    public function constancia(Evento $evento, string $motivo): string
    {
        return "Errata sobre {$evento->codigo()}: {$motivo}";
    }

    /**
     * Valor legible de un campo para mostrar en pantalla.
     */
    public function legible(string $campo, ?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($campo) {
            'tipo_evento_id' => TipoEvento::find($valor)?->nombre ?? "#{$valor}",
            'punto_monitoreo_id' => ($p = PuntoMonitoreo::find($valor)) ? "{$p->codigo} — {$p->nombre}" : "#{$valor}",
            'fecha_hora' => Carbon::parse($valor)->format('d/m/Y H:i:s'),
            default => $valor,
        };
    }
}
