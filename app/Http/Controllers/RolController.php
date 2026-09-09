<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Vista de SOLO LECTURA de roles y sus permisos (rol Administrativo).
 * La edición de permisos por rol es una fase posterior.
 */
class RolController extends Controller
{
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
        ]);
    }
}
