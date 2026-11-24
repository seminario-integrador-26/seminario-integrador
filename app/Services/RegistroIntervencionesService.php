<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\TipoEvento;
use DomainException;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * "Registro de Intervenciones" mensual para el Portal de Transparencia de la
 * Municipalidad: el mismo .xlsx que hoy se arma a mano
 * (intervenciones-del-centro-de-monitoreo-<mes>-<año>.xlsx).
 *
 * Para respetar el formato exacto (estilos, anchos, bordes, fórmulas de
 * subtotal y total) no se dibuja la hoja: se completa una copia en blanco de la
 * planilla oficial (resources/plantillas/registro-intervenciones.xlsx) con la
 * cantidad de novedades vigentes (con fe de errata aplicada) por tipo y por día.
 * Los días sin novedades quedan en blanco, como en la planilla original.
 */
class RegistroIntervencionesService
{
    public const PLANTILLA = 'plantillas/registro-intervenciones.xlsx';

    /**
     * Fila de la planilla => [código del GrupoEvento, nombre del TipoEvento]
     * (TipoEventoSeeder::CATALOGO). "Otros" existe en Policía y en Guardia
     * Local, por eso se identifica también por grupo.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const FILAS = [
        // CONCEPTO POLICIAL (subtotal en la fila 21)
        7 => ['policia', 'Personas sospechosas'],
        8 => ['policia', 'Vehículos sospechosos'],
        9 => ['policia', 'Personas aprehendidas'],
        10 => ['policia', 'Robos'],
        11 => ['policia', 'Hurtos'],
        12 => ['policia', 'Disturbios en vía pública'],
        13 => ['policia', 'Vandalismos'],
        14 => ['policia', 'Violencia familiar'],
        15 => ['policia', 'Arrebato'],
        16 => ['policia', 'Daño a propiedad pública/privada'],
        17 => ['policia', 'Persona en estado de ebriedad/sustancias'],
        18 => ['policia', 'Persona en situación de calle'],
        19 => ['policia', 'Resistencia a la autoridad'],
        20 => ['policia', 'Otros'],
        // SEGURIDAD CIUDADANA (subtotal en la fila 42)
        23 => ['guardia_local', 'Camiones en zona prohibida'],
        24 => ['guardia_local', 'Vehículos mal estacionados'],
        25 => ['guardia_local', 'Animales sueltos'],
        26 => ['guardia_local', 'Accidentes'],
        27 => ['guardia_local', 'Consumo de alcohol en vía pública'],
        28 => ['guardia_local', 'Incendios'],
        29 => ['guardia_local', 'Semáforos cortados'],
        30 => ['guardia_local', 'Alumbrado público'],
        31 => ['guardia_local', 'Saliente de agua'],
        32 => ['guardia_local', 'Pescadores'],
        33 => ['guardia_local', 'Restos de poda'],
        34 => ['guardia_local', 'Venta de alcohol'],
        35 => ['guardia_local', 'Obstáculo en la vía pública'],
        36 => ['guardia_local', 'Terrenos con maleza alta y falta de higiene'],
        37 => ['guardia_local', 'Transgrede vallado'],
        38 => ['guardia_local', 'Quema de pastizales'],
        39 => ['guardia_local', 'Vehículo descompuesto'],
        40 => ['guardia_local', 'Vehículo en estado de abandono'],
        41 => ['guardia_local', 'Otros'],
        // OFICIOS RECEPCIONADOS (subtotal en la fila 47)
        44 => ['internos', 'Oficios judiciales'],
        45 => ['internos', 'Particulares'],
        46 => ['internos', 'Solicitudes de seguros'],
    ];

    /** Filas con los números de día (encabezado de cada bloque). */
    private const FILAS_DIAS = [6, 22, 43];

    /** Columna B = día 1 … columna AF = día 31. */
    private const PRIMERA_COLUMNA = 2;

    /** Título (celda A5) y nombre de la hoja, como en la planilla oficial ("OCTUBRE 2026", "OCT."). */
    private const MESES = [
        1 => ['ENERO', 'ENE.'],
        2 => ['FEBRERO', 'FEB.'],
        3 => ['MARZO', 'MAR.'],
        4 => ['ABRIL', 'ABR.'],
        5 => ['MAYO', 'MAY.'],
        6 => ['JUNIO', 'JUN.'],
        7 => ['JULIO', 'JUL.'],
        8 => ['AGOSTO', 'AGO.'],
        // TODO: confirmar con el equipo la abreviatura de septiembre (SEP. o SET.).
        9 => ['SEPTIEMBRE', 'SEP.'],
        10 => ['OCTUBRE', 'OCT.'],
        11 => ['NOVIEMBRE', 'NOV.'],
        12 => ['DICIEMBRE', 'DIC.'],
    ];

    /**
     * Cantidad de novedades por fila de la planilla y por día del mes.
     * Solo los tipos cuantificables (regla 4) y los valores vigentes (regla 1).
     *
     * @return array<int, array<int, int>> fila => [día => cantidad]
     */
    public function conteos(int $anio, int $mes): array
    {
        [$desde, $hasta] = $this->rango($anio, $mes);

        $filaPorTipo = $this->filaPorTipo();

        $conteos = array_fill_keys(array_keys(self::FILAS), []);

        Evento::vigentes()
            ->whereIn('eventos.tipo_evento_id', array_keys($filaPorTipo))
            ->whereBetween('eventos.fecha_hora', [$desde, $hasta])
            ->toBase()
            ->select(['eventos.tipo_evento_id', 'eventos.fecha_hora'])
            ->orderBy('eventos.id')
            ->lazy()
            ->each(function (object $e) use (&$conteos, $filaPorTipo) {
                $fila = $filaPorTipo[$e->tipo_evento_id];
                $dia = (int) Carbon::parse($e->fecha_hora)->day;
                $conteos[$fila][$dia] = ($conteos[$fila][$dia] ?? 0) + 1;
            });

        return $conteos;
    }

    /**
     * Resumen para la pantalla: total del mes por concepto, en el orden de la planilla.
     *
     * @return array{titulo: string, total: int, bloques: list<array{titulo: string, subtotal: int, filas: list<array{concepto: string, total: int}>}>}
     */
    public function resumen(int $anio, int $mes): array
    {
        $conteos = $this->conteos($anio, $mes);
        $plantilla = $this->plantilla()->getActiveSheet();

        $bloques = [];
        foreach ([[6, 7, 20], [22, 23, 41], [43, 44, 46]] as [$encabezado, $desde, $hasta]) {
            $filas = [];
            for ($f = $desde; $f <= $hasta; $f++) {
                $filas[] = [
                    'concepto' => (string) $plantilla->getCell("A{$f}")->getValue(),
                    'total' => array_sum($conteos[$f]),
                ];
            }

            $bloques[] = [
                'titulo' => (string) $plantilla->getCell("A{$encabezado}")->getValue(),
                'subtotal' => array_sum(array_column($filas, 'total')),
                'filas' => $filas,
            ];
        }

        return [
            'titulo' => $this->titulo($anio, $mes),
            'total' => array_sum(array_column($bloques, 'subtotal')),
            'bloques' => $bloques,
        ];
    }

    /**
     * @return array{nombre: string, contenido: string, cantidad: int}
     */
    public function generar(int $anio, int $mes): array
    {
        $conteos = $this->conteos($anio, $mes);
        $libro = $this->plantilla();
        $hoja = $libro->getActiveSheet();

        $hoja->setTitle(self::MESES[$mes][1]);
        $hoja->setCellValue('A5', $this->titulo($anio, $mes));

        // Meses de menos de 31 días: la columna queda, sin número de día.
        $diasDelMes = Carbon::create($anio, $mes, 1)->daysInMonth;
        for ($dia = $diasDelMes + 1; $dia <= 31; $dia++) {
            foreach (self::FILAS_DIAS as $fila) {
                $hoja->getCell([self::PRIMERA_COLUMNA + $dia - 1, $fila])->setValue(null);
            }
        }

        foreach ($conteos as $fila => $porDia) {
            foreach ($porDia as $dia => $cantidad) {
                $hoja->getCell([self::PRIMERA_COLUMNA + $dia - 1, $fila])->setValue($cantidad);
            }
        }

        $libro->getProperties()
            ->setCreator('Centro de Monitoreo — Municipalidad de Villa María')
            ->setLastModifiedBy('Centro de Monitoreo — Municipalidad de Villa María')
            ->setTitle('Registro de Intervenciones '.$this->titulo($anio, $mes))
            ->setCreated(now()->getTimestamp())
            ->setModified(now()->getTimestamp());

        $writer = new Xlsx($libro);
        $writer->setPreCalculateFormulas(true); // los totales se ven aunque el visor no recalcule

        ob_start();
        $writer->save('php://output');
        $contenido = (string) ob_get_clean();

        $libro->disconnectWorksheets();

        return [
            'nombre' => sprintf(
                'intervenciones-del-centro-de-monitoreo-%s-%d.xlsx',
                mb_strtolower(self::MESES[$mes][0]),
                $anio,
            ),
            'contenido' => $contenido,
            'cantidad' => array_sum(array_map('array_sum', $conteos)),
        ];
    }

    /**
     * @throws DomainException si el mes todavía no empezó.
     */
    public function validarMes(int $anio, int $mes): void
    {
        if (Carbon::create($anio, $mes, 1)->startOfDay()->isAfter(now())) {
            throw new DomainException('El mes elegido todavía no empezó.');
        }
    }

    private function titulo(int $anio, int $mes): string
    {
        return self::MESES[$mes][0].' '.$anio;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rango(int $anio, int $mes): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();

        return [$desde, $desde->copy()->endOfMonth()];
    }

    /**
     * tipo_evento_id => fila de la planilla (solo tipos cuantificables).
     *
     * @return array<int, int>
     */
    private function filaPorTipo(): array
    {
        $filaPorClave = [];
        foreach (self::FILAS as $fila => [$grupo, $tipo]) {
            $filaPorClave["{$grupo}|{$tipo}"] = $fila;
        }

        return TipoEvento::query()
            ->with('grupoEvento:id,codigo')
            ->where('es_cuantificable', true)
            ->get(['id', 'nombre', 'grupo_evento_id'])
            ->mapWithKeys(fn (TipoEvento $t) => [
                $t->id => $filaPorClave[$t->grupoEvento?->codigo.'|'.$t->nombre] ?? null,
            ])
            ->filter()
            ->all();
    }

    private function plantilla(): Spreadsheet
    {
        return IOFactory::createReader('Xlsx')->load(resource_path(self::PLANTILLA));
    }
}
