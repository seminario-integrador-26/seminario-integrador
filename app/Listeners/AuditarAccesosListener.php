<?php

namespace App\Listeners;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Auth\AuditoriaAccesoService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * OBSERVER: eventos de autenticación -> log de auditoría (US-001).
 * Se ejecuta en sincrónico a propósito: la traza debe quedar escrita aunque
 * la cola esté caída.
 */
class AuditarAccesosListener
{
    public function __construct(private readonly AuditoriaAccesoService $auditoria) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        $this->auditoria->registrar(
            AuditoriaAcceso::EVENTO_LOGIN,
            $user->getAttribute('email') ?? '',
            $user instanceof User ? $user : null,
        );
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user;

        $this->auditoria->registrar(
            AuditoriaAcceso::EVENTO_LOGIN_FALLIDO,
            (string) ($event->credentials['email'] ?? ''),
            $user instanceof User ? $user : null,
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if ($user === null) {
            return;
        }

        $this->auditoria->registrar(
            AuditoriaAcceso::EVENTO_LOGOUT,
            $user->getAttribute('email') ?? '',
            $user instanceof User ? $user : null,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email', '');

        $this->auditoria->registrar(
            AuditoriaAcceso::EVENTO_BLOQUEO,
            $email,
            User::where('email', $email)->first(),
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            Lockout::class => 'handleLockout',
        ];
    }
}
