<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US-001: log de auditoría de accesos al sistema.
 * Registro inmutable (sin updated_at): se escribe una fila por evento de
 * autenticación y nunca se modifica.
 */
#[Fillable(['user_id', 'email', 'evento', 'ip_address', 'user_agent'])]
class AuditoriaAcceso extends Model
{
    public const EVENTO_LOGIN = 'login';

    public const EVENTO_LOGIN_FALLIDO = 'login_fallido';

    public const EVENTO_LOGOUT = 'logout';

    public const EVENTO_BLOQUEO = 'bloqueo';

    protected $table = 'auditoria_accesos';

    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
