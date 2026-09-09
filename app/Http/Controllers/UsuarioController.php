<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use App\Services\UsuarioService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de usuarios (rol Administrativo). Controller fino: delega en
 * UsuarioService. Ver CLAUDE.md.
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(): Response
    {
        $usuarios = $this->usuarios->listar()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'rol' => $u->roles->first()?->name,
            'created_at' => $u->created_at?->format('d/m/Y'),
        ]);

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'authUserId' => Auth::id(),
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
        $this->usuarios->crear($request->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('Usuarios/Edit', [
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->roles->first()?->name,
            ],
            'roles' => RoleSeeder::ROLES,
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        // Evita que un Administrativo se quite a sí mismo el rol (auto-bloqueo).
        if ($usuario->id === Auth::id() && $request->validated()['rol'] !== 'Administrativo') {
            return back()->with('error', 'No podés cambiar tu propio rol de Administrativo.');
        }

        $this->usuarios->actualizar($usuario, $request->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === Auth::id()) {
            return back()->with('error', 'No podés eliminar tu propia cuenta.');
        }

        $this->usuarios->eliminar($usuario);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado.');
    }
}
