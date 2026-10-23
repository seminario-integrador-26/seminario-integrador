<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * "Fe de errata": único mecanismo de corrección de un Evento. Tabla separada,
 * NO una columna en eventos. Referencia al evento original sin modificarlo.
 * Inmutable, como el evento: no se edita ni se borra.
 *
 * Desde 2026-10-23 registra solo el motivo (campo_corregido null): los datos
 * del evento quedan bloqueados. Las erratas anteriores guardan además
 * campo_corregido, valor_anterior y valor_nuevo, y siguen aplicando en los
 * valores vigentes.
 */
#[Fillable(['evento_id', 'usuario_id', 'campo_corregido', 'valor_anterior', 'valor_nuevo', 'motivo'])]
class Errata extends Model
{
    /**
     * Campos que corregían las erratas anteriores (columna => etiqueta).
     */
    public const CAMPOS = [
        'tipo_evento_id' => 'Tipo de evento',
        'fecha_hora' => 'Fecha y horario del hecho',
        'punto_monitoreo_id' => 'Punto de monitoreo',
        'timestamp_video' => 'Timestamp del video',
        'descripcion' => 'Descripción',
    ];

    public const UPDATED_AT = null;

    protected $table = 'erratas';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las fe de erratas son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las fe de erratas no se eliminan.'));
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
