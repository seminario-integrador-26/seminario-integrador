<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TurnoService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Roles y permisos del usuario logueado, para gatear la UI en React.
                'roles' => $user ? $user->getRoleNames() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            ],
            // US-025: turno de guardia abierto del Supervisor (o null), para que el
            // modal del Dashboard y el nav reaccionen en cualquier pantalla.
            'turnoSupervisor' => fn () => $user && $user->hasRole('Supervisor')
                ? $this->turnoDelSupervisor($user)
                : null,
            // Mensajes flash para feedback en la UI.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Turno activo del supervisor, aplanado para la UI, o null si no tiene uno.
     *
     * @return array{id: int, fecha: string, hora_inicio: string}|null
     */
    private function turnoDelSupervisor(User $user): ?array
    {
        $turno = app(TurnoService::class)->activoDe($user);

        return $turno ? [
            'id' => $turno->id,
            'fecha' => $turno->fecha->format('d/m/Y'),
            'hora_inicio' => substr((string) $turno->hora_inicio, 0, 5),
        ] : null;
    }
}
