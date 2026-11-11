<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ampliación: los datos del evento quedan bloqueados; solo se registra el
 * detalle que se agrega.
 */
class StoreAmpliacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ruta gateada por role:Supervisor; el permiso lo controla
        // AmpliacionController.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'detalle' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['detalle' => 'detalle de la ampliación'];
    }
}
