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
        'eventos.consultar' => ['Supervisor', 'Administrativo', 'Administrador de sistema'],
        'erratas.crear' => ['Supervisor'],
        'estadisticas.ver' => ['Administrativo', 'Administrador de sistema'],
        'dashboards.ver' => ['Administrativo', 'Administrador de sistema'],
        'reportes.exportar' => ['Administrativo', 'Administrador de sistema'],
        // Gestión reservada al Administrador de sistema (el Administrativo es solo lectura).
        'usuarios.gestionar' => ['Administrador de sistema'],
        'tipos_evento.gestionar' => ['Administrador de sistema'],
        'puntos.gestionar' => ['Administrador de sistema'],
        // US-028: grupos de WhatsApp y su correspondencia con los tipos de evento.
        'notificaciones.configurar' => ['Administrador de sistema'],
        // US-029: parámetros generales (sesión, bloqueo, refresco, reintentos).
        'parametros.configurar' => ['Administrador de sistema'],
    ];

    /**
     * Permisos que ya no existen: sin tabla de turnos el Supervisor consulta
     * todo el libro y abre/cierra el turno registrando un evento (2026-10-23).
     */
    private const OBSOLETOS = ['eventos.consultar_historico', 'turnos.gestionar'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::OBSOLETOS)->delete();

        // permisos por rol, invirtiendo la matriz.
        $porRol = [];

        foreach (self::MATRIZ as $permiso => $roles) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);

            foreach ($roles as $rol) {
                $porRol[$rol][] = $permiso;
            }
        }

        // sync y no give: la matriz es la fuente de verdad, así que volver a
        // correr el seeder también QUITA lo que un rol no debería tener. Con
        // givePermissionTo un permiso mal asignado quedaba pegado para siempre.
        foreach ($porRol as $rol => $permisos) {
            Role::where('name', $rol)->first()?->syncPermissions($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
