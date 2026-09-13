<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * US-001: bloqueo temporal de la cuenta tras MAX_INTENTOS fallidos.
 * El estado se persiste en la cuenta (users.intentos_fallidos /
 * users.bloqueado_hasta), no en caché por IP: el bloqueo sigue al usuario.
 */
class BloqueoCuentaService
{
    public const MAX_INTENTOS = 3;

    public const MINUTOS_BLOQUEO = 15;

    public function estaBloqueada(User $user): bool
    {
        return $user->bloqueado_hasta !== null
            && $user->bloqueado_hasta->isFuture();
    }

    /**
     * Segundos que faltan para que se libere la cuenta (0 si no está bloqueada).
     */
    public function segundosRestantes(User $user): int
    {
        if (! $this->estaBloqueada($user)) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($user->bloqueado_hasta, absolute: true));
    }

    /**
     * Suma un intento fallido y bloquea la cuenta al llegar al máximo.
     *
     * @return bool true si este intento disparó el bloqueo
     */
    public function registrarFallo(User $user): bool
    {
        // Un bloqueo vencido arranca de cero el conteo.
        if ($user->bloqueado_hasta !== null && $user->bloqueado_hasta->isPast()) {
            $user->intentos_fallidos = 0;
            $user->bloqueado_hasta = null;
        }

        $user->intentos_fallidos++;

        $bloquea = $user->intentos_fallidos >= self::MAX_INTENTOS;

        if ($bloquea) {
            $user->bloqueado_hasta = Carbon::now()->addMinutes(self::MINUTOS_BLOQUEO);
        }

        $user->save();

        return $bloquea;
    }

    /**
     * Login exitoso: limpia contador y bloqueo.
     */
    public function limpiar(User $user): void
    {
        if ($user->intentos_fallidos === 0 && $user->bloqueado_hasta === null) {
            return;
        }

        $user->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();
    }
}
