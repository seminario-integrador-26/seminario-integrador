<?php

namespace App\Services;

use App\Jobs\EnviarNotificacionWhatsapp;
use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\GrupoEvento;
use App\Models\GrupoInteresado;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\Auth\AuditoriaAccesoService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * US-006 / US-007: notificación automática a los grupos interesados.
 *
 * Arma el mensaje, crea una Notificacion por grupo (registro del resultado) y
 * despacha el Job en cola que hace el envío real con reintentos. También
 * resuelve el reenvío manual y deja la traza en la bitácora de auditoría.
 */
class NotificacionService
{
    public function __construct(private readonly AuditoriaAccesoService $auditoria) {}

    /**
     * Una notificación por cada grupo asociado al tipo del evento.
     *
     * // TODO: confirmar con el equipo a quién se notifican los Informativos
     * //       (hoy: a quien tenga asociado el tipo; por defecto, a nadie).
     *
     * @return Collection<int, Notificacion>
     */
    public function notificarEvento(Evento $evento): Collection
    {
        $evento->loadMissing(['tipoEvento.gruposInteresados', 'tipoEvento.grupoEvento', 'puntoMonitoreo.tipoPuntoMonitoreo', 'barrio']);
        $mensaje = $this->armarMensaje($evento);

        return $evento->tipoEvento->gruposInteresados->map(function (GrupoInteresado $grupo) use ($evento, $mensaje) {
            $notificacion = Notificacion::create([
                'evento_id' => $evento->id,
                'grupo_interesado_id' => $grupo->id,
                'canal' => config('notificaciones.canal'),
                'mensaje' => $mensaje,
                'estado' => Notificacion::ESTADO_PENDIENTE,
            ]);

            EnviarNotificacionWhatsapp::dispatch($notificacion);

            return $notificacion;
        });
    }

    /**
     * Mensaje de US-006, en el formato que ya usaba la guardia por WhatsApp
     * (decisión 2026-10-28): una línea corta, fácil de leer y de buscar.
     *
     *   06.56hs - Se observa por cámara Nº 41 ...
     *   26/10 06.56hs - ...                    (si no es de hoy)
     *   07.00hs - Apertura de turno: ...      (tipos Internos)
     */
    public function armarMensaje(Evento $evento): string
    {
        $tipo = $evento->tipoEvento;
        $momento = $evento->fecha_hora->format('H.i').'hs';

        if (! $evento->fecha_hora->isToday()) {
            $momento = $evento->fecha_hora->format('d/m').' '.$momento;
        }

        // Los Internos (turno, Nota, Errata...) no se reconocen por la descripción.
        // La Apertura suma franja y grupo: "Apertura de turno (Mañana · Grupo 2): ...".
        $guardia = $evento->guardia() ? " ({$evento->guardia()})" : '';
        $texto = $tipo->grupoEvento?->codigo === GrupoEvento::INTERNOS
            ? "{$tipo->nombre}{$guardia}: {$evento->descripcion}"
            : (string) $evento->descripcion;

        // Telegram corta en 4096 caracteres.
        return Str::limit("{$momento} - {$texto}", 4000);
    }

    public function marcarEnviada(Notificacion $notificacion): void
    {
        $notificacion->update([
            'estado' => Notificacion::ESTADO_ENVIADA,
            'error' => null,
            'enviada_at' => now(),
        ]);
    }

    /**
     * Agotados los reintentos (US-007): se marca fallida y se audita. El
     * Supervisor la ve como alerta y puede reenviarla.
     */
    public function marcarFallida(Notificacion $notificacion, Throwable $error): void
    {
        $notificacion->update([
            'estado' => Notificacion::ESTADO_FALLIDA,
            'error' => Str::limit($error->getMessage(), 1000),
        ]);

        $notificacion->loadMissing('grupoInteresado:id,nombre');

        $this->auditoria->registrar(
            AuditoriaAcceso::EVENTO_NOTIF_FALLIDA,
            'sistema',
            descripcion: Str::limit(
                "Notificación #{$notificacion->id} de la novedad #{$notificacion->evento_id} "
                ."a {$notificacion->grupoInteresado->nombre} falló tras {$notificacion->intentos} intento(s): "
                .$error->getMessage(),
                500,
            ),
            rol: 'Sistema',
        );
    }

    /**
     * US-007: reenvío manual de una notificación fallida.
     *
     * @throws DomainException si la notificación no está fallida.
     */
    public function reenviar(Notificacion $notificacion, User $actor): void
    {
        if ($notificacion->estado !== Notificacion::ESTADO_FALLIDA) {
            throw new DomainException('Solo se pueden reenviar notificaciones fallidas.');
        }

        $notificacion->update([
            'estado' => Notificacion::ESTADO_PENDIENTE,
            'error' => null,
        ]);

        $notificacion->loadMissing('grupoInteresado:id,nombre');

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_NOTIF_REENVIO,
            $actor,
            "Notificación #{$notificacion->id} de la novedad #{$notificacion->evento_id} "
            ."reenviada a {$notificacion->grupoInteresado->nombre}.",
        );

        EnviarNotificacionWhatsapp::dispatch($notificacion);
    }

    /**
     * Notificaciones fallidas de los eventos que registró el Supervisor
     * (alerta visual de US-007).
     */
    public function fallidasDe(User $supervisor): int
    {
        return Notificacion::where('estado', Notificacion::ESTADO_FALLIDA)
            ->whereHas('evento', fn ($q) => $q->where('usuario_id', $supervisor->id))
            ->count();
    }
}
