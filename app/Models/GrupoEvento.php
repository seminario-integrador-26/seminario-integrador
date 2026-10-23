<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupa los tipos de evento (la "categoría" del registro de intervenciones):
 * Policía, Guardia Local, Internos.
 * `codigo` es la clave estable que usan filtros y frontend.
 */
#[Fillable(['codigo', 'nombre', 'descripcion'])]
class GrupoEvento extends Model
{
    protected $table = 'grupos_evento';

    public function tiposEvento(): HasMany
    {
        return $this->hasMany(TipoEvento::class);
    }

    /**
     * Opciones para selects y filtros: [codigo => nombre].
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return static::orderBy('id')->pluck('nombre', 'codigo')->all();
    }
}
