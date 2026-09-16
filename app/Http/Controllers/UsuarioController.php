<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\UsuarioService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de usuarios (rol Administrador de sistema). Controller fino: delega en
 * UsuarioService. Ver CLAUDE.md.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $usuarios,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    public function index(): Response
    {
        $usuarios = $this->usuarios->listar()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'roles' => $u->roles->pluck('name'),
            'created_at' => $u->created_at?->format('d/m/Y'),
        ]);

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'authUserId' => Auth::id(),
            'roles' => RoleSeeder::ROLES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Usuarios/Create', [
            'roles' => RoleSeeder::ROLES,
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->usuarios->crear($data);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_ALTA_USUARIO,
            $request->user(),
            "Alta de usuario {$data['email']} con rol(es) ".implode(', ', $data['roles']).'.',
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('Usuarios/Edit', [
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'username' => $usuario->username,
                'email' => $usuario->email,
                'roles' => $usuario->roles->pluck('name'),
            ],
            'roles' => RoleSeeder::ROLES,
            // Entre Administradores de sistema no se blanquean la clave:
            // esa cuenta recupera su contraseña por email.
            'puedeResetPassword' => ! $usuario->hasRole('Administrador de sistema'),
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $data = $request->validated();

        // Evita que un Administrador de sistema se quite a sí mismo ese rol (auto-bloqueo).
        if ($usuario->id === Auth::id() && ! in_array('Administrador de sistema', $data['roles'], true)) {
            return back()->with('error', 'No podés quitarte a vos mismo el rol de Administrador de sistema.');
        }

        // Entre Administradores de sistema no se blanquean la clave: esa cuenta
        // recupera su contraseña por email.
        if ($usuario->hasRole('Administrador de sistema') && ! empty($data['password'])) {
            return back()->with('error', 'No se puede blanquear la contraseña de un Administrador de sistema. Debe recuperarla por email.');
        }

        $this->usuarios->actualizar($usuario, $data);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_MOD_USUARIO,
            $request->user(),
            "Modificación de usuario {$data['email']} (rol(es) ".implode(', ', $data['roles']).').',
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Designación rápida de roles desde el listado (sin abrir el formulario).
     */
    public function cambiarRoles(Request $request, User $usuario): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleSeeder::ROLES)],
        ]);

        // Mismo auto-bloqueo que en update(): un Administrador de sistema no
        // puede quitarse a sí mismo ese rol.
        if ($usuario->id === Auth::id() && ! in_array('Administrador de sistema', $data['roles'], true)) {
            return back()->with('error', 'No podés quitarte a vos mismo el rol de Administrador de sistema.');
        }

        $this->usuarios->cambiarRoles($usuario, $data['roles']);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_MOD_USUARIO,
            $request->user(),
            "Roles de {$usuario->email} actualizados a ".implode(', ', $data['roles']).'.',
        );

        return back()->with('success', "Roles de {$usuario->name} actualizados.");
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No podés eliminar tu propia cuenta.');
        }

        // Los eventos son inmutables y referencian a su autor y turno: no se puede borrar.
        if ($usuario->eventos()->exists() || $usuario->turnos()->exists()) {
            return back()->with('error', 'No se puede eliminar un usuario con eventos o turnos registrados.');
        }

        $emailEliminado = $usuario->email;
        $this->usuarios->eliminar($usuario);

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_BAJA_USUARIO,
            Auth::user(),
            "Baja de usuario {$emailEliminado}.",
        );

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado.');
    }
}
