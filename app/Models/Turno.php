<?php

namespace App\Models;

use Database\Factories\TurnoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de guardia. Campos: supervisor_id, fecha, hora_inicio, hora_fin.
 * Abierto mientras hora_fin es null.
 */
#[Fillable(['supervisor_id', 'fecha', 'hora_inicio', 'hora_fin'])]
class Turno extends Model
{
    /** @use HasFactory<TurnoFactory> */
    use HasFactory;

    protected $table = 'turnos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function scopeAbierto(Builder $query): void
    {
        $query->whereNull('hora_fin');
    }

    /**
     * Turno de guardia abierto al momento de la consulta.
     *
     * // TODO: confirmar con el equipo si puede haber más de un turno abierto
     * // (p. ej. uno por Supervisor). Hoy se toma el último abierto, global.
     */
    public static function activo(): ?self
    {
        return static::abierto()->latest('id')->first();
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
