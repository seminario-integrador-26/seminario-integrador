<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio 5: no se puede registrar un evento sin un turno de guardia abierto.
 */
class SinTurnoActivoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hay un turno de guardia abierto. Abrí un turno antes de registrar eventos.');
    }
}
