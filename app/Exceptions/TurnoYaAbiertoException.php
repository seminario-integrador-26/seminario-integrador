<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * US-025: sólo puede haber un turno de guardia abierto por Supervisor a la vez.
 */
class TurnoYaAbiertoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya tenés un turno de guardia abierto. Cerralo antes de abrir uno nuevo.');
    }
}
