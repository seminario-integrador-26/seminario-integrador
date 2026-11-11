<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ampliación de una novedad: información que se suma después de registrada
 * (p. ej. "la persona fue aprehendida a dos cuadras"). Como la fe de errata,
 * es un registro separado que referencia al evento sin modificarlo, y es
 * inmutable: no se edita ni se borra. A diferencia de la errata, no corrige
 * nada: amplía.
 */
#[Fillable(['evento_id', 'usuario_id', 'detalle'])]
class Ampliacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ampliaciones';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las ampliaciones son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las ampliaciones no se eliminan.'));
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
