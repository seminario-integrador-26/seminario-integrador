<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * US-025: validación del cierre de un turno de guardia.
 * La fecha/hora de fin la fija TurnoService.
 */
class CerrarTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está gateada por middleware role:Supervisor.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // TODO: confirmar con el equipo si las novedades son obligatorias al cerrar.
            'novedades_pendientes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
