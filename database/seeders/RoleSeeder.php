<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles del sistema (spatie/laravel-permission). SÓLO estos tres:
 *   - Supervisor:    registra eventos, fe de errata, abre/cierra turnos.
 *   - Administrativo: consulta, estadísticas, dashboards, export, admin usuarios/tipos.
 *   - Operador:      solo lectura (dashboards); no carga eventos.
 *
 * No agregar otros roles (ver CLAUDE.md).
 */
class RoleSeeder extends Seeder
{
    public const ROLES = ['Supervisor', 'Administrativo', 'Operador'];

    public function run(): void
    {
        // Limpia la caché de permisos antes de sembrar.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // TODO: cuando existan las entidades, definir y asignar permisos
        // granulares por rol (registrar_evento, exportar, administrar_usuarios, etc.).
    }
}
