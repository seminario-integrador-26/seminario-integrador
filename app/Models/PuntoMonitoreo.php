<?php

namespace App\Models;

use Database\Factories\PuntoMonitoreoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Punto de Monitoreo (PM): ubicación física de una cámara.
 * Campos: codigo, nombre, jurisdiccion (municipal | provincial), latitud, longitud.
 * latitud/longitud alimentan Leaflet + OpenStreetMap en el frontend.
 */
#[Fillable(['codigo', 'nombre', 'jurisdiccion', 'latitud', 'longitud'])]
class PuntoMonitoreo extends Model
{
    /** @use HasFactory<PuntoMonitoreoFactory> */
    use HasFactory;

    public const JURISDICCIONES = [
        'municipal' => 'Municipal',
        'provincial' => 'Provincial',
    ];

    protected $table = 'puntos_monitoreo';

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
