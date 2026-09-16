<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lógica de gestión de roles y permisos (usada por el rol Administrador de
 * sistema). Toda la escritura sobre permisos pasa por acá (SRP).
 *
 * OJO: la fuente de verdad "de fábrica" de la matriz permiso→rol sigue siendo
 * PermissionSeeder. Un `db:seed --class=PermissionSeeder` (o migrate:fresh
 * --seed) vuelve a sincronizar y PISA lo que se toque desde esta UI.
 */
class RolService
{
    /**
     * Permiso que el rol Administrador de sistema no puede perder: sin él,
     * nadie podría volver a entrar a la gestión de usuarios/roles (auto-bloqueo).
     */
    public const PERMISO_IRRENUNCIABLE = 'usuarios.gestionar';

    public const ROL_ADMIN = 'Administrador de sistema';

    /**
     * Todos los permisos existentes, ordenados por nombre.
     *
     * @return Collection<int, string>
     */
    public function permisosDisponibles(): Collection
    {
        return Permission::orderBy('name')->pluck('name');
    }

    /**
     * Crea un permiso nuevo (guard 'web'). Idempotente.
     */
    public function crearPermiso(string $name): Permission
    {
        $permiso = Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permiso;
    }

    /**
     * Sincroniza el set de permisos de un rol. Para el Administrador de sistema
     * fuerza el permiso irrenunciable aunque no venga marcado (anti-bloqueo).
     *
     * @param  array<int, string>  $permisos
     */
    public function sincronizarPermisos(Role $rol, array $permisos): void
    {
        if ($rol->name === self::ROL_ADMIN && ! in_array(self::PERMISO_IRRENUNCIABLE, $permisos, true)) {
            $permisos[] = self::PERMISO_IRRENUNCIABLE;
        }

        $rol->syncPermissions($permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
