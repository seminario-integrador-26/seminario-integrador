<?php

namespace App\Models;

use Database\Factories\TipoEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campos: nombre, categoria (ver CATEGORIAS),
 * requiere_ubicacion (bool)  -> si false, el Evento puede no tener PuntoMonitoreo.
 * es_cuantificable (bool)     -> si false, NO cuenta en estadísticas.
 * Ambos flags son independientes entre sí.
 *
 * Relación N:M con GrupoInteresado (a quién se notifica según clasificación).
 */
#[Fillable(['nombre', 'categoria', 'requiere_ubicacion', 'es_cuantificable'])]
class TipoEvento extends Model
{
    /** @use HasFactory<TipoEventoFactory> */
    use HasFactory;

    /**
     * Ejes de clasificación (clave persistida => etiqueta).
     */
    public const CATEGORIAS = [
        'prevencion' => 'Prevención',
        'convivencia_urbana' => 'Convivencia Urbana',
        'seguridad_publica' => 'Seguridad Pública',
        'informativo' => 'Informativo',
    ];

    protected $table = 'tipos_evento';

    protected function casts(): array
    {
        return [
            'requiere_ubicacion' => 'boolean',
            'es_cuantificable' => 'boolean',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    // TODO: gruposInteresados() (belongsToMany) cuando exista GrupoInteresado.
}
