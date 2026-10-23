<?php

namespace App\Models;

use Database\Factories\TipoEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campos: grupo_evento_id (ver GrupoEvento), nombre (único por grupo), descripcion,
 * requiere_ubicacion (bool)  -> si false, el Evento puede no tener PuntoMonitoreo.
 * es_cuantificable (bool)     -> si false, NO cuenta en estadísticas.
 * notifica (bool)             -> si true, al registrarse se avisa por WhatsApp. Derivado:
 *                                true si tiene grupos de WhatsApp asignados (US-028).
 * Los flags son independientes entre sí.
 */
#[Fillable(['grupo_evento_id', 'nombre', 'descripcion', 'requiere_ubicacion', 'es_cuantificable', 'notifica'])]
class TipoEvento extends Model
{
    /** @use HasFactory<TipoEventoFactory> */
    use HasFactory;

    /**
     * Tipos del grupo Internos que arman el libro de guardia: el turno se abre
     * y se cierra registrando un evento (docs/rediseno-libro-guardia.md).
     */
    public const APERTURA_TURNO = 'Apertura de turno';

    public const CIERRE_TURNO = 'Cierre de turno';

    protected $table = 'tipos_evento';

    /**
     * Id de un tipo del grupo Internos por nombre (o null si no está sembrado).
     */
    public static function interno(string $nombre): ?int
    {
        return static::where('nombre', $nombre)
            ->whereHas('grupoEvento', fn ($q) => $q->where('codigo', GrupoEvento::INTERNOS))
            ->value('id');
    }

    protected function casts(): array
    {
        return [
            'requiere_ubicacion' => 'boolean',
            'es_cuantificable' => 'boolean',
            'notifica' => 'boolean',
        ];
    }

    public function grupoEvento(): BelongsTo
    {
        return $this->belongsTo(GrupoEvento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Grupos de WhatsApp a los que se notifica al registrar un evento de este
     * tipo (US-028). `notifica` se deriva de acá: true si tiene al menos un
     * grupo (lo mantiene GrupoInteresadoService).
     */
    public function gruposInteresados(): BelongsToMany
    {
        return $this->belongsToMany(GrupoInteresado::class, 'grupo_interesado_tipo_evento');
    }
}
