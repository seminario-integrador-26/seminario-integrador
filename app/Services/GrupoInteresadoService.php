<?php

namespace App\Services;

use App\Models\GrupoInteresado;
use App\Models\TipoEvento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * US-028: grupos destinatarios de WhatsApp y su correspondencia con los tipos
 * de evento. Los cambios rigen para el próximo evento: el listener lee la
 * correspondencia al momento de notificar, no hay caché.
 *
 * "Fuerza de respuesta" (Bomberos, Guardia Local, Policía) se modela como el
 * propio grupo destinatario: no hay derivación (regla 6 de CLAUDE.md).
 */
class GrupoInteresadoService
{
    /**
     * Reglas del formulario de grupo. Hace falta un destino: el chat_id del
     * grupo de Telegram, el identificador del grupo de WhatsApp o al menos un
     * contacto.
     *
     * @return array<string, mixed>
     */
    public static function reglas(?int $ignorarId = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('grupos_interesados', 'nombre')->ignore($ignorarId)],
            'identificador_whatsapp' => ['nullable', 'string', 'max:100', 'required_without_all:contactos,telegram_chat_id'],
            // chat_id numérico (los grupos son negativos) o @canal público.
            'telegram_chat_id' => ['nullable', 'string', 'max:50', 'regex:/^(-?\d+|@\w{5,})$/'],
            'contactos' => ['array', 'max:50'],
            'contactos.*.nombre' => ['required', 'string', 'max:100'],
            // Formato internacional sin "+": 54 9 353 4000000.
            'contactos.*.numero_whatsapp' => ['required', 'regex:/^\d{10,15}$/', 'distinct'],
            'tipos_evento' => ['array'],
            'tipos_evento.*' => ['integer', 'exists:tipos_evento,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'nombre.unique' => 'Ya existe un grupo con ese nombre.',
            'identificador_whatsapp.required_without_all' => 'Indicá el chat de Telegram, el grupo de WhatsApp o cargá al menos un contacto.',
            'telegram_chat_id.regex' => 'El chat_id es un número (los grupos empiezan con "-", ej.: -1001234567890) o un @canal.',
            'contactos.*.nombre.required' => 'Falta el nombre del contacto.',
            'contactos.*.numero_whatsapp.required' => 'Falta el número.',
            'contactos.*.numero_whatsapp.regex' => 'Solo dígitos, con código de país y sin "+" (ej.: 5493534000000).',
            'contactos.*.numero_whatsapp.distinct' => 'Número repetido.',
        ];
    }

    /**
     * Alta o modificación del grupo, sus contactos y los tipos que recibe.
     * Devuelve el detalle de lo que cambió, para la auditoría.
     *
     * @param  array<string, mixed>  $datos
     */
    public function guardar(GrupoInteresado $grupo, array $datos): string
    {
        return DB::transaction(function () use ($grupo, $datos) {
            $grupo->fill([
                'nombre' => $datos['nombre'],
                'identificador_whatsapp' => $datos['identificador_whatsapp'] ?? null,
                'telegram_chat_id' => $datos['telegram_chat_id'] ?? null,
            ]);
            $cambios = array_keys($grupo->getDirty());
            $grupo->save();

            $contactos = collect($datos['contactos'] ?? [])
                ->map(fn ($c) => ['nombre' => trim($c['nombre']), 'numero_whatsapp' => $c['numero_whatsapp']]);
            $anteriores = $grupo->contactos()->orderBy('id')->get(['nombre', 'numero_whatsapp'])
                ->map(fn ($c) => ['nombre' => $c->nombre, 'numero_whatsapp' => $c->numero_whatsapp]);

            if ($contactos->values()->all() !== $anteriores->values()->all()) {
                $grupo->contactos()->delete();
                $grupo->contactos()->createMany($contactos->all());
                $cambios[] = 'contactos';
            }

            $tipos = $grupo->tiposEvento()->sync($datos['tipos_evento'] ?? []);

            if ($tipos['attached'] || $tipos['detached']) {
                $cambios[] = 'tipos de novedad ('.$this->describirTipos($tipos['attached'], $tipos['detached']).')';
                $this->sincronizarNotifica();
            }

            return implode(', ', $cambios);
        });
    }

    /**
     * Guarda la matriz completa tipo de evento × grupo.
     *
     * @param  list<array{tipo_evento_id: int, grupo_interesado_id: int}>  $pares
     * @return string detalle de altas y bajas ('' si no cambió nada)
     */
    public function actualizarCorrespondencias(array $pares): string
    {
        return DB::transaction(function () use ($pares) {
            $nuevos = collect($pares)->groupBy('grupo_interesado_id')
                ->map(fn ($filas) => $filas->pluck('tipo_evento_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

            $detalle = [];

            foreach (GrupoInteresado::orderBy('nombre')->get() as $grupo) {
                $resultado = $grupo->tiposEvento()->sync($nuevos->get($grupo->id, []));

                if ($resultado['attached'] || $resultado['detached']) {
                    $detalle[] = "{$grupo->nombre}: ".$this->describirTipos($resultado['attached'], $resultado['detached']);
                }
            }

            $this->sincronizarNotifica();

            return implode('; ', $detalle);
        });
    }

    /**
     * La matriz manda: TipoEvento.notifica = tiene al menos un grupo asignado.
     * Se recalcula después de cada cambio de correspondencias o baja de grupo.
     */
    public function sincronizarNotifica(): void
    {
        TipoEvento::query()->update([
            'notifica' => DB::raw('exists (select 1 from grupo_interesado_tipo_evento p where p.tipo_evento_id = tipos_evento.id)'),
        ]);
    }

    /**
     * "+Robo, −Incendio" a partir de los ids agregados y quitados.
     *
     * @param  list<int>  $agregados
     * @param  list<int>  $quitados
     */
    private function describirTipos(array $agregados, array $quitados): string
    {
        $nombres = TipoEvento::whereIn('id', [...$agregados, ...$quitados])->pluck('nombre', 'id');

        return Str::limit(implode(', ', [
            ...array_map(fn ($id) => '+'.$nombres[$id], $agregados),
            ...array_map(fn ($id) => '−'.$nombres[$id], $quitados),
        ]), 300);
    }
}
