<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles del sistema (spatie/laravel-permission). SÓLO estos tres:
 *   - Supervisor:    registra eventos (incluida la apertura y el cierre de turno) y fe de errata.
 *   - Administrativo: SOLO visualización — consulta eventos, estadísticas,
 *                     dashboards y exporta (.xlsx/PDF). No gestiona usuarios ni tipos.
 *   - Administrador de sistema:    todo lo del Administrativo + gestiona usuarios y tipos de evento.
 *
 * No agregar otros roles (ver CLAUDE.md).
 */
class RoleSeeder extends Seeder
{
    public const ROLES = ['Supervisor', 'Administrativo', 'Administrador de sistema'];

    public function run(): void
    {
        // Limpia la caché de permisos antes de sembrar.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Los permisos granulares y su asignación por rol: PermissionSeeder.
    }
}
