<?php

namespace App\Http\Requests;

use App\Models\TipoEvento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * US-003: validación del registro de un evento.
 * El autor (usuario_id) y el turno NO vienen del request: los fija EventoService.
 */
class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está gateada por middleware role:Supervisor.
        return true;
    }

    /**
     * <input type="time"> omite los segundos cuando son :00 -> se normaliza a hh:mm:ss.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hora' => $this->conSegundos($this->input('hora')),
            'timestamp_video' => $this->conSegundos($this->input('timestamp_video')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_evento_id' => ['required', 'integer', 'exists:tipos_evento,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i:s'],
            'punto_monitoreo_id' => [
                'nullable',
                // Regla de negocio 3: ubicación condicional según el tipo.
                Rule::requiredIf(fn () => (bool) $this->tipoEvento()?->requiere_ubicacion),
                'integer',
                'exists:puntos_monitoreo,id',
            ],
            'timestamp_video' => [
                // Sin PM no hay video asociado: el campo se descarta.
                Rule::excludeIf(fn () => blank($this->input('punto_monitoreo_id'))),
                'required',
                'date_format:H:i:s',
            ],
            // TODO: confirmar con el equipo si la descripción es obligatoria.
            'descripcion' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * El hecho no puede ser posterior al momento de la carga.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['fecha', 'hora'])) {
                    return;
                }

                $fechaHora = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->input('fecha')} {$this->input('hora')}");

                if ($fechaHora->isFuture()) {
                    $validator->errors()->add('fecha', 'La fecha y hora del hecho no pueden ser posteriores al momento actual.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'punto_monitoreo_id.required' => 'El tipo de evento seleccionado requiere un punto de monitoreo.',
            'punto_monitoreo_id.exists' => 'El punto de monitoreo seleccionado no existe.',
            'timestamp_video.required' => 'El timestamp del video es obligatorio cuando se indica un punto de monitoreo.',
            'timestamp_video.date_format' => 'El timestamp del video debe tener el formato hh:mm:ss.',
        ];
    }

    private function tipoEvento(): ?TipoEvento
    {
        $id = $this->input('tipo_evento_id');

        return is_numeric($id) ? TipoEvento::find($id) : null;
    }

    private function conSegundos(mixed $hora): mixed
    {
        return is_string($hora) && preg_match('/^\d{2}:\d{2}$/', $hora) ? "{$hora}:00" : $hora;
    }
}
