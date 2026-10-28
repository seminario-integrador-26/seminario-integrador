<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de auditoría del sistema (trazabilidad).
 * Nació para los accesos (US-001) y hoy registra también las acciones de
 * dominio. Registro INMUTABLE (sin updated_at): una fila por acción, nunca se
 * modifica ni se borra.
 */
#[Fillable(['user_id', 'email', 'rol', 'evento', 'descripcion', 'ip_address', 'user_agent'])]
class AuditoriaAcceso extends Model
{
    // Accesos (US-001).
    public const EVENTO_LOGIN = 'login';

    public const EVENTO_LOGIN_FALLIDO = 'login_fallido';

    public const EVENTO_LOGOUT = 'logout';

    public const EVENTO_BLOQUEO = 'bloqueo';

    // Acciones de dominio.
    public const EVENTO_REGISTRO_EVENTO = 'evento_registrado';

    public const EVENTO_ALTA_USUARIO = 'usuario_creado';

    public const EVENTO_MOD_USUARIO = 'usuario_actualizado';

    public const EVENTO_BAJA_USUARIO = 'usuario_eliminado';

    public const EVENTO_ALTA_PERMISO = 'permiso_creado';

    public const EVENTO_MOD_PERMISOS = 'permisos_actualizados';

    public const EVENTO_TURNO_APERTURA = 'turno_abierto';

    public const EVENTO_TURNO_CIERRE = 'turno_cerrado';

    // Notificaciones (US-007).
    public const EVENTO_NOTIF_FALLIDA = 'notificacion_fallida';

    public const EVENTO_NOTIF_REENVIO = 'notificacion_reenviada';

    // Reportes y exportaciones (US-012, US-013).
    public const EVENTO_REPORTE_PDF = 'reporte_pdf';

    public const EVENTO_EXPORTACION = 'exportacion_datos';

    // Fe de errata (regla de negocio 1).
    public const EVENTO_ERRATA = 'errata_registrada';

    // Puntos de monitoreo (US-024).
    public const EVENTO_PM_ALTA = 'punto_creado';

    public const EVENTO_PM_MOD = 'punto_actualizado';

    public const EVENTO_PM_BAJA = 'punto_eliminado';

    public const EVENTO_PM_IMPORT = 'puntos_importados';

    // Grupos de notificación y correspondencias (US-028).
    public const EVENTO_GRUPO_ALTA = 'grupo_creado';

    public const EVENTO_GRUPO_MOD = 'grupo_actualizado';

    public const EVENTO_GRUPO_BAJA = 'grupo_eliminado';

    public const EVENTO_CORRESPONDENCIAS = 'correspondencias_actualizadas';

    // Parámetros del sistema (US-029).
    public const EVENTO_PARAMETROS = 'parametros_actualizados';

    // Consulta del propio log (US-027).
    public const EVENTO_AUDITORIA_CONSULTA = 'auditoria_consultada';

    // Tokens de la API pública de Datos Abiertos.
    public const EVENTO_TOKEN_ALTA = 'token_api_emitido';

    public const EVENTO_TOKEN_BAJA = 'token_api_revocado';

    /**
     * Etiquetas legibles para la UI.
     *
     * @var array<string, string>
     */
    public const ACCIONES = [
        self::EVENTO_LOGIN => 'Inicio de sesión',
        self::EVENTO_LOGIN_FALLIDO => 'Intento fallido',
        self::EVENTO_LOGOUT => 'Cierre de sesión',
        self::EVENTO_BLOQUEO => 'Cuenta bloqueada',
        self::EVENTO_REGISTRO_EVENTO => 'Novedad registrada',
        self::EVENTO_ALTA_USUARIO => 'Usuario creado',
        self::EVENTO_MOD_USUARIO => 'Usuario actualizado',
        self::EVENTO_BAJA_USUARIO => 'Usuario eliminado',
        self::EVENTO_ALTA_PERMISO => 'Permiso creado',
        self::EVENTO_MOD_PERMISOS => 'Permisos de rol actualizados',
        self::EVENTO_TURNO_APERTURA => 'Turno abierto',
        self::EVENTO_TURNO_CIERRE => 'Turno cerrado',
        self::EVENTO_NOTIF_FALLIDA => 'Notificación fallida',
        self::EVENTO_NOTIF_REENVIO => 'Notificación reenviada',
        self::EVENTO_REPORTE_PDF => 'Reporte PDF generado',
        self::EVENTO_EXPORTACION => 'Exportación de datos',
        self::EVENTO_ERRATA => 'Fe de errata',
        self::EVENTO_PM_ALTA => 'PM creado',
        self::EVENTO_PM_MOD => 'PM actualizado',
        self::EVENTO_PM_BAJA => 'PM eliminado',
        self::EVENTO_PM_IMPORT => 'PMs importados',
        self::EVENTO_GRUPO_ALTA => 'Grupo creado',
        self::EVENTO_GRUPO_MOD => 'Grupo actualizado',
        self::EVENTO_GRUPO_BAJA => 'Grupo eliminado',
        self::EVENTO_CORRESPONDENCIAS => 'Correspondencias actualizadas',
        self::EVENTO_PARAMETROS => 'Parámetros actualizados',
        self::EVENTO_AUDITORIA_CONSULTA => 'Consulta del log',
        self::EVENTO_TOKEN_ALTA => 'Token de API emitido',
        self::EVENTO_TOKEN_BAJA => 'Token de API revocado',
    ];

    protected $table = 'auditoria_accesos';

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
