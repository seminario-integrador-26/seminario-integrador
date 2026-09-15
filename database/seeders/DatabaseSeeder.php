<?php

namespace Database\Seeders;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            TipoEventoSeeder::class,
            PuntoMonitoreoSeeder::class,
        ]);

        // Un usuario de desarrollo por cada rol (solo para entornos locales).
        // El slug quita espacios/acentos para que el email sea válido
        // (p. ej. "Administrador de sistema" -> "administradordesistema").
        foreach (RoleSeeder::ROLES as $role) {
            $slug = (string) Str::of($role)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '');

            User::factory()
                ->create([
                    'name' => $role,
                    'username' => $slug,
                    'email' => "{$slug}@example.com",
                ])
                ->assignRole($role);
        }

        // Turno abierto para poder registrar eventos en desarrollo
        // (la apertura/cierre desde la UI es CU01, pendiente).
        Turno::create([
            'supervisor_id' => User::where('email', 'supervisor@example.com')->value('id'),
            'fecha' => now()->toDateString(),
            'hora_inicio' => now()->format('H:i:s'),
        ]);
    }
}
