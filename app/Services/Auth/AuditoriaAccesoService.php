<?php

namespace App\Services\Auth;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * US-001: escribe el log de auditoría de accesos (usuario, fecha y hora).
 * Única vía de escritura sobre auditoria_accesos (SRP).
 */
class AuditoriaAccesoService
{
    public function __construct(private readonly Request $request) {}

    public function registrar(string $evento, string $email, ?User $user = null): AuditoriaAcceso
    {
        return AuditoriaAcceso::create([
            'user_id' => $user?->getKey(),
            'email' => $email,
            'evento' => $evento,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]);
    }
}
