<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Lógica de gestión de usuarios y sus roles (usada por el rol Administrador de sistema).
 * Toda la escritura sobre usuarios/roles pasa por acá (SRP).
 */
class UsuarioService
{
    /**
     * Usuarios con su rol, para el listado.
     *
     * @return Collection<int, User>
     */
    public function listar(): Collection
    {
        return User::with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'created_at']);
    }

    /**
     * Crea un usuario y le asigna uno o varios roles.
     *
     * @param  array{name: string, username: string, email: string, password: string, roles: list<string>}  $data
     */
    public function crear(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles']);

        return $user;
    }

    /**
     * Actualiza datos del usuario y sus roles. La password es opcional.
     *
     * @param  array{name: string, username: string, email: string, roles: list<string>, password?: string|null}  $data
     */
    public function actualizar(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return $user;
    }

    /**
     * Reemplaza el set de roles del usuario (designación rápida desde el listado).
     *
     * @param  list<string>  $roles
     */
    public function cambiarRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user;
    }

    public function eliminar(User $user): void
    {
        $user->delete();
    }
}
