<?php

namespace Database\Seeders;

use App\Models\GrupoEvento;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Catálogo real de grupos y tipos de evento, tomado del "Registro de
 * Intervenciones" del Centro de Control Videoperativo (planilla octubre 2025).
 * Idempotente: crea lo que falta por codigo de grupo y por (grupo, nombre) del tipo.
 *
 * No se siembran correspondencias de notificación: el Admin de sistema arma la
 * matriz cuando haya grupos reales (decisión del equipo, 2026-10-15).
 * // TODO: confirmar con el equipo si algún tipo es no cuantificable (la
 * // planilla los cuenta a todos).
 */
class TipoEventoSeeder extends Seeder
{
    /**
     * codigo => [nombre, requiere_ubicacion, tipos]
     *
     * @var array<string, array{0: string, 1: bool, 2: list<string>}>
     */
    public const CATALOGO = [
        'policia' => ['Policía', true, [
            'Personas sospechosas',
            'Vehículos sospechosos',
            'Personas aprehendidas',
            'Robos',
            'Hurtos',
            'Disturbios en vía pública',
            'Vandalismos',
            'Violencia familiar',
            'Arrebato',
            'Daño a propiedad pública/privada',
            'Persona en estado de ebriedad/sustancias',
            'Persona en situación de calle',
            'Resistencia a la autoridad',
            'Otros',
        ]],
        'guardia_local' => ['Guardia Local', true, [
            'Camiones en zona prohibida',
            'Vehículos mal estacionados',
            'Animales sueltos',
            'Accidentes',
            'Consumo de alcohol en vía pública',
            'Incendios',
            'Semáforos cortados',
            'Alumbrado público',
            'Saliente de agua',
            'Pescadores',
            'Restos de poda',
            'Venta de alcohol',
            'Obstáculo en la vía pública',
            'Terrenos con maleza alta y falta de higiene',
            'Transgrede vallado',
            'Quema de pastizales',
            'Vehículo descompuesto',
            'Vehículo en estado de abandono',
            'Otros',
        ]],
        // Ocurren en la sala (oficios recibidos, libro de guardia): no se ubican en un PM.
        'internos' => ['Internos', false, [
            'Oficios judiciales',
            'Particulares',
            'Solicitudes de seguros',
            'Apertura de turno',
            'Cierre de turno',
            'Nota',
            'Errata',
            'Otros',
        ]],
    ];

    /**
     * Eventos internos del libro de guardia: se registran y se notifican, pero
     * no entran en estadísticas (docs/rediseno-libro-guardia.md).
     * // TODO: confirmar con el equipo si los oficios también dejan de contarse.
     *
     * @var list<string>
     */
    public const INTERNOS_NO_CUANTIFICABLES = ['Apertura de turno', 'Cierre de turno', 'Nota', 'Errata', 'Otros'];

    /** Tipos de ejemplo que cargaba la versión anterior de este seeder. */
    private const TIPOS_DE_EJEMPLO = [
        'Persona en actitud sospechosa',
        'Ruidos molestos',
        'Disturbios en vía pública',
        'Robo / hurto',
        'Accidente de tránsito',
        'Falla de cámara',
        'Novedad de guardia',
    ];

    public function run(): void
    {
        // deploy.sh corre este seeder en cada deploy: firstOrCreate para no pisar
        // lo que el Administrador de sistema edite después desde la UI.
        foreach (self::CATALOGO as $codigo => [$nombreGrupo, $requiereUbicacion, $tipos]) {
            $grupo = GrupoEvento::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombreGrupo]);

            foreach ($tipos as $nombre) {
                TipoEvento::firstOrCreate(
                    ['grupo_evento_id' => $grupo->id, 'nombre' => $nombre],
                    [
                        // "Otros" no siempre ocurre frente a una cámara.
                        'requiere_ubicacion' => $requiereUbicacion && $nombre !== 'Otros',
                        'es_cuantificable' => ! ($codigo === 'internos' && in_array($nombre, self::INTERNOS_NO_CUANTIFICABLES, true)),
                    ],
                );
            }
        }

        // Los de ejemplo se borran solo si no tienen eventos (los eventos son
        // inmutables y el FK es restrictivo). "Disturbios en vía pública" ya
        // existe en el catálogo real dentro de Policía: se conserva ese.
        $policia = GrupoEvento::where('codigo', 'policia')->value('id');
        TipoEvento::whereIn('nombre', self::TIPOS_DE_EJEMPLO)
            ->whereNot(fn ($q) => $q->where('nombre', 'Disturbios en vía pública')->where('grupo_evento_id', $policia))
            ->doesntHave('eventos')
            ->delete();
    }
}
