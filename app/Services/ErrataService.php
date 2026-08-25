<?php

namespace App\Services;

/**
 * Único mecanismo de corrección permitido sobre un Evento.
 *
 * SINGLE RESPONSIBILITY: crear registros de "fe de errata".
 * REGLA DE NEGOCIO: el Evento original NUNCA se sobreescribe. Una Errata es
 * un registro separado que referencia al evento y guarda campo_corregido,
 * valor_anterior, valor_nuevo, motivo y quién la hizo.
 */
class ErrataService
{
    // TODO: registrar(Evento $evento, string $campo, $anterior, $nuevo, string $motivo, int $usuarioId): Errata
}
