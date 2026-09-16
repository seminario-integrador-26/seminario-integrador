<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Asigna un rol (Supervisor|Administrativo|Administrador de sistema) a un usuario.
 * Si el usuario no existe y se pasa --password, lo crea.
 *
 * Herramienta de consola para administrar roles mientras no exista la UI
 * de administración de usuarios (rol Administrador de sistema). Ver CLAUDE.md.
 */
class AssignUserRole extends Command
{
    protected $signature = 'user:role
        {email : Email del usuario}
        {role : Rol a asignar (Supervisor, Administrativo o Administrador de sistema)}
        {--name= : Nombre (solo al crear un usuario nuevo)}
        {--username= : Nombre de usuario para login (por defecto, el local part del email)}
        {--password= : Password (requerido para crear un usuario nuevo)}';

    protected $description = 'Asigna un rol a un usuario; lo crea si no existe y se pasa --password';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (! in_array($role, RoleSeeder::ROLES, true)) {
            $this->error("Rol inválido: '{$role}'. Debe ser uno de: ".implode(', ', RoleSeeder::ROLES));

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = $this->option('password');

            if (! $password) {
                $this->error("El usuario '{$email}' no existe. Pasá --password para crearlo.");

                return self::FAILURE;
            }

            $username = $this->option('username')
                ?: (string) Str::of($email)->before('@')->ascii()->lower()->replaceMatches('/[^a-z0-9._-]/', '');

            $user = User::create([
                'name' => $this->option('name') ?: $email,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info("Usuario creado: {$email} (usuario: {$username})");
        }

        // syncRoles deja al usuario SOLO con este rol (un rol por usuario).
        $user->syncRoles([$role]);

        $this->info("Rol '{$role}' asignado a {$email}.");

        return self::SUCCESS;
    }
}
