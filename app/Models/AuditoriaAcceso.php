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
        self::EVENTO_REGISTRO_EVENTO => 'Evento registrado',
        self::EVENTO_ALTA_USUARIO => 'Usuario creado',
        self::EVENTO_MOD_USUARIO => 'Usuario actualizado',
        self::EVENTO_BAJA_USUARIO => 'Usuario eliminado',
        self::EVENTO_ALTA_PERMISO => 'Permiso creado',
        self::EVENTO_MOD_PERMISOS => 'Permisos de rol actualizados',
    ];

    protected $table = 'auditoria_accesos';

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
