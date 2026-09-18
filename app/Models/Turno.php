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
 * Turno de guardia. Campos: supervisor_id, fecha, hora_inicio, hora_fin,
 * personal_presente (lista de nombres), fecha_fin y novedades_pendientes.
 * Abierto mientras hora_fin es null.
 */
#[Fillable([
    'supervisor_id', 'fecha', 'hora_inicio', 'hora_fin',
    'personal_presente', 'fecha_fin', 'novedades_pendientes',
])]
class Turno extends Model
{
    /** @use HasFactory<TurnoFactory> */
    use HasFactory;

    protected $table = 'turnos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_fin' => 'date',
            'personal_presente' => 'array',
        ];
    }

    public function scopeAbierto(Builder $query): void
    {
        $query->whereNull('hora_fin');
    }

    public function scopeDeSupervisor(Builder $query, int $supervisorId): void
    {
        $query->where('supervisor_id', $supervisorId);
    }

    public function estaAbierto(): bool
    {
        return $this->hora_fin === null;
    }

    /**
     * Último turno abierto, global. Sólo para el panorama del Dashboard (SOC).
     * Para la lógica de negocio usar activoDe(): un turno activo por Supervisor
     * (US-025).
     */
    public static function activo(): ?self
    {
        return static::abierto()->latest('id')->first();
    }

    /**
     * US-025: turno de guardia abierto de un Supervisor (a lo sumo uno).
     */
    public static function activoDe(int $supervisorId): ?self
    {
        return static::abierto()->deSupervisor($supervisorId)->latest('id')->first();
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
