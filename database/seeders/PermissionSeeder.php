<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo BASE de permisos y su asignación a roles, derivado de la matriz de
 * responsabilidades documentada en CLAUDE.md. Es un punto de partida:
 *
 * // TODO: confirmar con el equipo el set final de permisos granulares.
 *
 * Corre después de RoleSeeder (los roles ya deben existir).
 */
class PermissionSeeder extends Seeder
{
    /**
     * permiso => roles que lo tienen.
     *
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        'eventos.registrar' => ['Supervisor'],
        'eventos.consultar' => ['Supervisor', 'Administrativo'],
        'erratas.crear' => ['Supervisor'],
        'turnos.gestionar' => ['Supervisor'],
        'estadisticas.ver' => ['Administrativo', 'Operador'],
        'dashboards.ver' => ['Administrativo', 'Operador'],
        'reportes.exportar' => ['Administrativo'],
        'usuarios.gestionar' => ['Administrativo'],
        'tipos_evento.gestionar' => ['Administrativo'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIZ as $permiso => $roles) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);

            foreach ($roles as $rol) {
                Role::where('name', $rol)->first()?->givePermissionTo($permiso);
            }
        }
    }
}
