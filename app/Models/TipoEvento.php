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

    /** Nota de guardia: aviso interno, no cuenta en estadísticas. */
    public const NOTA = 'Nota';

    /**
     * Tipos Internos sin cámara asociada: no llevan punto de monitoreo,
     * barrio ni dirección. Se notifican a todos los grupos por defecto.
     */
    public const SIN_CAMARA = [self::APERTURA_TURNO, self::CIERRE_TURNO, self::NOTA];

    /**
     * Constancia que crea el sistema al cargar una fe de errata: no se
     * registra a mano ni se le hace errata.
     */
    public const ERRATA = 'Errata';

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

    /**
     * Ids de los tipos sin cámara (Apertura, Cierre de turno y Nota) que se
     * notifican a todos los grupos por defecto.
     *
     * @return list<int>
     */
    public static function sinCamaraIds(): array
    {
        return array_values(array_filter(array_map(static::interno(...), self::SIN_CAMARA)));
    }

    /**
     * Apertura, Cierre de turno o Nota (del grupo Internos): sin ubicación.
     */
    public function sinCamara(): bool
    {
        return in_array($this->nombre, self::SIN_CAMARA, true)
            && $this->grupoEvento?->codigo === GrupoEvento::INTERNOS;
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
    /**
     * Apertura o Cierre de turno (del grupo Internos).
     */
    public function esDeTurno(): bool
    {
        return $this->sinCamara() && $this->nombre !== self::NOTA;
    }

    public function gruposInteresados(): BelongsToMany
    {
        return $this->belongsToMany(GrupoInteresado::class, 'grupo_interesado_tipo_evento');
    }
}
