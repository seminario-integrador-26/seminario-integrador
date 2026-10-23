<?php

namespace App\Http\Requests\Concerns;

use App\Models\TipoEvento;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas de los datos de un evento (US-003 / US-005) para el registro
 * (StoreEventoRequest). La fe de errata ya no cambia datos: solo pide el motivo.
 */
trait ValidaDatosDeEvento
{
    /**
     * <input type="time"> omite los segundos cuando son :00 -> se normaliza a hh:mm:ss.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hora' => $this->conSegundos($this->input('hora')),
            'timestamp_video' => $this->conSegundos($this->input('timestamp_video')),
        ]);

        // Apertura y Cierre de turno no tienen cámara: sin PM ni timestamp.
        if ($this->tipoEvento()?->sinCamara()) {
            $this->merge(['punto_monitoreo_id' => null, 'timestamp_video' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglasDeEvento(): array
    {
        return [
            'tipo_evento_id' => [
                'required',
                'integer',
                'exists:tipos_evento,id',
                // La constancia Errata la crea el sistema al cargar una fe de errata.
                Rule::notIn(array_filter([TipoEvento::interno(TipoEvento::ERRATA)])),
            ],
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
            // US-005: la descripción no puede estar vacía.
            'descripcion' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
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

                // US-005: el hecho no puede ser posterior al momento de la carga.
                if ($fechaHora->isFuture()) {
                    $validator->errors()->add('fecha', 'La fecha y hora del hecho no pueden ser posteriores al momento actual.');
                }

                $timestamp = $this->input('timestamp_video');

                if (filled($this->input('punto_monitoreo_id')) && is_string($timestamp)
                    && ! $validator->errors()->has('timestamp_video')
                    && ! $this->timestampCoherente($this->input('hora'), $timestamp)) {
                    $validator->errors()->add('timestamp_video', 'El timestamp del video no puede ser posterior al horario del hecho.');
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
            'tipo_evento_id.not_in' => 'El tipo Errata lo registra el sistema al cargar una fe de errata.',
            'punto_monitoreo_id.required' => 'El tipo de evento seleccionado requiere un punto de monitoreo.',
            'punto_monitoreo_id.exists' => 'El punto de monitoreo seleccionado no existe.',
            'timestamp_video.required' => 'El timestamp del video es obligatorio cuando se indica un punto de monitoreo.',
            'timestamp_video.date_format' => 'El timestamp del video debe tener el formato hh:mm:ss.',
            'descripcion.required' => 'La descripción no puede estar vacía.',
        ];
    }

    /**
     * US-005: el momento del video (lo que muestra la grabación) no puede ser
     * posterior al horario del hecho. Excepción: hecho en la primera hora del
     * día y video en la última del anterior (la grabación cruzó la medianoche).
     *
     * // TODO: confirmar con el informante la tolerancia exacta a medianoche.
     */
    private function timestampCoherente(string $hora, string $timestamp): bool
    {
        if ($timestamp <= $hora) {
            return true;
        }

        return $hora < '01:00:00' && $timestamp >= '23:00:00';
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
