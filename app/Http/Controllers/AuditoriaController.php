<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora de auditoría — vista de SOLO LECTURA para el Administrador de sistema
 * (gateada por permission:usuarios.gestionar). Muestra los movimientos de los
 * usuarios: quién, con qué rol, desde qué IP y qué acción hizo.
 */
class AuditoriaController extends Controller
{
    public function index(): Response
    {
        $registros = AuditoriaAcceso::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->through(fn (AuditoriaAcceso $a) => [
                'id' => $a->id,
                'fecha_hora' => $a->created_at?->format('d/m/Y H:i:s'),
                'usuario' => $a->user?->name ?? $a->email,
                'email' => $a->email,
                'rol' => $a->rol,
                'accion' => AuditoriaAcceso::ACCIONES[$a->evento] ?? $a->evento,
                'ip' => $a->ip_address,
                'descripcion' => $a->descripcion,
            ]);

        return Inertia::render('Usuarios/Auditoria', [
            'registros' => $registros,
        ]);
    }
}
