<?php

namespace App\Http\Middleware;

use App\Services\NotificacionService;
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
            // US-007: notificaciones fallidas de los eventos del Supervisor (alerta visual).
            'notificacionesFallidas' => fn () => $user && $user->hasRole('Supervisor')
                ? app(NotificacionService::class)->fallidasDe($user)
                : 0,
            // Mensajes flash para feedback en la UI.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // US-003: id del evento recién registrado (enlace "Ver evento").
                'eventoRegistrado' => fn () => $request->session()->get('eventoRegistrado'),
                // US-024: detalle fila por fila de una importación de PMs rechazada.
                'erroresImportacion' => fn () => $request->session()->get('erroresImportacion'),
            ],
        ];
    }
}
