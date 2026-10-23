<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fe de errata: los datos del evento quedan bloqueados; solo se registra el
 * motivo de la corrección.
 */
class StoreErrataRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ruta gateada por role:Supervisor; el alcance (evento visible) lo
        // controla ErrataController.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
