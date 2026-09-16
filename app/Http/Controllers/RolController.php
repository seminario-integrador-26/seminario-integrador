<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\RolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Gestión de roles y sus permisos (rol Administrador de sistema). Controller
 * fino: delega la escritura en RolService. Ver CLAUDE.md.
 */
class RolController extends Controller
{
    public function __construct(
        private readonly RolService $roles,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(): Response
    {
        $roles = Role::with('permissions:id,name')
            ->get(['id', 'name'])
            ->map(fn (Role $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'permisos' => $rol->permissions->pluck('name'),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permisosDisponibles' => $this->roles->permisosDisponibles(),
            'permisoIrrenunciable' => [
                'rol' => RolService::ROL_ADMIN,
                'permiso' => RolService::PERMISO_IRRENUNCIABLE,
            ],
        ]);
    }

    public function storePermiso(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Convención: dominio.accion en minúsculas (p. ej. eventos.consultar).
            'name' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z_]+\.[a-z_]+$/',
                Rule::unique('permissions', 'name'),
            ],
        ], [
            'name.regex' => 'Usá el formato dominio.accion en minúsculas (ej. reportes.exportar).',
        ]);

        $this->roles->crearPermiso($data['name']);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_ALTA_PERMISO,
            Auth::user(),
            "Alta del permiso {$data['name']}.",
        );

        return back()->with('success', "Permiso {$data['name']} creado.");
    }

    public function actualizarPermisos(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $this->roles->sincronizarPermisos($role, $data['permisos']);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_MOD_PERMISOS,
            Auth::user(),
            "Permisos del rol {$role->name} actualizados.",
        );

        return back()->with('success', "Permisos del rol {$role->name} actualizados.");
    }
}
