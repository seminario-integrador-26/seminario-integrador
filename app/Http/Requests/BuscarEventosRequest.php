<?php

namespace App\Http\Requests;

use App\Models\TipoEvento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU04: filtros de la consulta de eventos. Todos opcionales.
 * La autorización la resuelve el middleware `permission:eventos.consultar`.
 */
class BuscarEventosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_evento_id' => ['nullable', 'integer', 'exists:tipos_evento,id'],
            'categoria' => ['nullable', Rule::in(array_keys(TipoEvento::CATEGORIAS))],
            'punto_monitoreo_id' => ['nullable', 'integer', 'exists:puntos_monitoreo,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }
}
