<?php

namespace App\Services\Auth;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Escribe la bitácora de auditoría (usuario, rol, IP, acción y detalle).
 * Única vía de escritura sobre auditoria_accesos (SRP). Cubre tanto los
 * accesos (US-001) como las acciones de dominio.
 */
class AuditoriaAccesoService
{
    public function __construct(private readonly Request $request) {}

    /**
     * Registra un evento de acceso/acción. Si no se pasa el rol, lo toma del
     * usuario (snapshot del rol al momento de la acción).
     */
    public function registrar(
        string $evento,
        string $email,
        ?User $user = null,
        ?string $descripcion = null,
        ?string $rol = null,
    ): AuditoriaAcceso {
        return AuditoriaAcceso::create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'rol' => $rol ?? $user?->getRoleNames()->first(),
            'evento' => $evento,
            'descripcion' => $descripcion,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]);
    }

    /**
     * Atajo para auditar una acción de dominio ejecutada por un usuario
     * autenticado (toma email y rol del actor).
     */
    public function registrarAccion(string $accion, User $actor, ?string $descripcion = null): AuditoriaAcceso
    {
        return $this->registrar($accion, (string) $actor->email, $actor, $descripcion);
    }
}
