<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * US-025: validación de la apertura de un turno de guardia.
 * El supervisor y la fecha/hora de inicio NO vienen del request: los fija
 * TurnoService.
 */
class AbrirTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está gateada por middleware role:Supervisor.
        return true;
    }

    /**
     * Descarta nombres vacíos del listado antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $personal = $this->input('personal_presente', []);

        if (is_array($personal)) {
            $this->merge([
                'personal_presente' => array_values(array_filter(
                    array_map(fn ($nombre) => is_string($nombre) ? trim($nombre) : $nombre, $personal),
                    fn ($nombre) => is_string($nombre) && $nombre !== '',
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'personal_presente' => ['required', 'array', 'min:1'],
            'personal_presente.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'personal_presente.required' => 'Indicá al menos una persona presente en la guardia.',
            'personal_presente.min' => 'Indicá al menos una persona presente en la guardia.',
            'personal_presente.*.required' => 'El nombre del personal no puede estar vacío.',
        ];
    }
}
