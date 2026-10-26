<?php

namespace App\Http\Requests\Concerns;

use App\Models\TipoEvento;
use App\Services\LibroGuardiaService;
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
            'direccion' => is_string($d = $this->input('direccion')) ? trim($d) : $d,
        ]);

        // Apertura y Cierre de turno no tienen cámara ni ubicación.
        if ($this->tipoEvento()?->sinCamara()) {
            $this->merge(['punto_monitoreo_id' => null, 'barrio_id' => null, 'direccion' => null]);
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
            'punto_monitoreo_id' => ['nullable', 'integer', 'exists:puntos_monitoreo,id'],
            // Regla de negocio 3: si el tipo exige ubicación, va un PM o, sin
            // PM, barrio y dirección. Con PM se descartan (el barrio sale del PM).
            'barrio_id' => [
                Rule::excludeIf(fn () => filled($this->input('punto_monitoreo_id'))),
                'nullable',
                Rule::requiredIf(fn () => (bool) $this->tipoEvento()?->requiere_ubicacion),
                'integer',
                'exists:barrios,id',
            ],
            'direccion' => [
                Rule::excludeIf(fn () => filled($this->input('punto_monitoreo_id'))),
                'nullable',
                Rule::requiredIf(fn () => (bool) $this->tipoEvento()?->requiere_ubicacion),
                'string',
                'max:255',
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
            },
            // Todo evento pertenece a un turno: sin Apertura vigente solo se
            // puede abrir el turno, y con una abierta no se abre otra.
            function (Validator $validator) {
                if ($validator->errors()->has('tipo_evento_id')) {
                    return;
                }

                $apertura = app(LibroGuardiaService::class)->aperturaVigente();
                $esApertura = $this->tipoEvento()?->id === TipoEvento::interno(TipoEvento::APERTURA_TURNO);

                if (! $apertura && ! $esApertura) {
                    $validator->errors()->add('tipo_evento_id', 'No hay un turno abierto: registrá primero la Apertura de turno.');
                } elseif ($apertura && $esApertura) {
                    $validator->errors()->add('tipo_evento_id', "Ya hay un turno abierto (Apertura #{$apertura->id}): registrá el Cierre de turno antes de abrir otro.");
                } elseif ($apertura && ! $validator->errors()->hasAny(['fecha', 'hora'])
                    && "{$this->input('fecha')} {$this->input('hora')}" < $apertura->fecha_hora->format('Y-m-d H:i:s')) {
                    $validator->errors()->add('fecha', 'El hecho no puede ser anterior a la apertura del turno ('.$apertura->fecha_hora->format('d/m/Y H:i:s').').');
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
            'punto_monitoreo_id.exists' => 'El punto de monitoreo seleccionado no existe.',
            'barrio_id.required' => 'Sin punto de monitoreo, indicá el barrio.',
            'barrio_id.exists' => 'El barrio seleccionado no existe.',
            'direccion.required' => 'Sin punto de monitoreo, indicá la dirección.',
            'descripcion.required' => 'La descripción no puede estar vacía.',
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
