<?php

namespace App\Models;

use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Evento detectado por videovigilancia. INMUTABLE tras su creación:
 * sin edición ni borrado; toda corrección va por Errata.
 *
 * Campos: tipo_evento_id, punto_monitoreo_id (nullable, ver requiere_ubicacion),
 * usuario_id (Supervisor autor), fecha_hora (del hecho),
 * timestamp_video (hh:mm:ss, obligatorio con PM), descripcion, created_at.
 * Sin PM: barrio_id y direccion (obligatorios si el tipo exige ubicación).
 */
#[Fillable([
    'tipo_evento_id',
    'punto_monitoreo_id',
    'barrio_id',
    'direccion',
    'usuario_id',
    'fecha_hora',
    'timestamp_video',
    'descripcion',
])]
class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos';

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    /**
     * Regla de negocio 1: el original nunca se sobreescribe ni se elimina.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos son inmutables: usar fe de errata.'));
        static::deleting(fn () => throw new LogicException('Los eventos no se eliminan: usar fe de errata.'));
    }

    /**
     * Consulta sobre los valores VIGENTES (original + fe de erratas), para
     * estadísticas, análisis, mapa, exportaciones y API. Lee la vista
     * eventos_vigentes con alias `eventos`, así las columnas calificadas
     * (eventos.fecha_hora) y las relaciones funcionan igual. Solo lectura:
     * los modelos que devuelve no se guardan (los eventos son inmutables).
     *
     * Con withCount() hay que poner antes ->select('eventos.*'): sin columnas,
     * withCount arma "<from>.*" y con el alias la consulta sale mal.
     *
     * @return Builder<static>
     */
    public static function vigentes(): Builder
    {
        return static::query()->from('eventos_vigentes', 'eventos');
    }

    /**
     * Código legible del evento: AT-<año>-<id con 4 dígitos>.
     */
    public function codigo(): string
    {
        return sprintf('AT-%s-%04d', $this->fecha_hora->format('Y'), $this->id);
    }

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(TipoEvento::class);
    }

    public function puntoMonitoreo(): BelongsTo
    {
        return $this->belongsTo(PuntoMonitoreo::class);
    }

    /** Barrio cargado a mano cuando el evento no tiene PM. */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    /**
     * Ubicación legible: el PM ("PM-M-001 — Plaza") o, sin PM, la dirección y
     * el barrio cargados a mano ("San Martín 123, barrio Centro"). Null si no
     * tiene ninguno. Requiere cargadas las relaciones puntoMonitoreo y barrio.
     */
    public function ubicacion(): ?string
    {
        if ($this->puntoMonitoreo) {
            return "{$this->puntoMonitoreo->codigo} — {$this->puntoMonitoreo->nombre}";
        }

        $partes = array_filter([
            $this->direccion,
            $this->barrio ? "barrio {$this->barrio->nombre}" : null,
        ]);

        return $partes ? implode(', ', $partes) : null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function erratas(): HasMany
    {
        return $this->hasMany(Errata::class)->orderBy('id');
    }

    /**
     * Valores vigentes de los campos corregibles: el original con las fe de
     * erratas aplicadas en orden. El registro original no se toca.
     *
     * @return array<string, mixed>
     */
    public function valoresVigentes(): array
    {
        $valores = [
            'tipo_evento_id' => $this->tipo_evento_id,
            'fecha_hora' => $this->fecha_hora->format('Y-m-d H:i:s'),
            'punto_monitoreo_id' => $this->punto_monitoreo_id,
            'timestamp_video' => $this->timestamp_video,
            'descripcion' => $this->descripcion,
        ];

        // Las erratas de solo motivo (sin campo) no cambian valores.
        foreach ($this->erratas->whereNotNull('campo_corregido') as $errata) {
            $valores[$errata->campo_corregido] = $errata->valor_nuevo;
        }

        return $valores;
    }
}
