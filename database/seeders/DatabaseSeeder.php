<?php

namespace Database\Seeders;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        foreach (RoleSeeder::ROLES as $role) {
            $slug = strtolower($role);

            User::factory()
                ->create([
                    'name' => $role,
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
