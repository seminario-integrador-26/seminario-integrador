import FechaInput from '@/Components/FechaInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Opciones } from '@/types';
import { Head, router } from '@inertiajs/react';
import { type ReactNode, useCallback, useEffect, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
    type TooltipProps,
} from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';

/** Tramo de tiempo con su rango (para el drill-down) y conteo por código de categoría. */
type Tramo = { clave: string; etiqueta: string; desde: string; hasta: string; total: number } & Record<
    string,
    string | number
>;

interface Kpi {
    total: number;
    anterior: number;
    /** % respecto del período anterior; null si no hay con qué comparar. */
    variacion: number | null;
}

/** Fila del ranking de zonas críticas; clave = id del PM o del barrio. */
interface Zona {
    clave: string;
    nombre: string;
    cantidad: number;
    porcentaje: number;
}

interface Hora {
    hora: number;
    /** hh */
    etiqueta: string;
    cantidad: number;
    porcentaje: number;
    destacada: boolean;
}

/** AnalisisService::calcular. */
interface Analisis {
    periodo: { desde: string; hasta: string };
    granularidad: string;
    kpis: {
        mes: Kpi;
        anio: Kpi;
        periodo: number;
        por_categoria: { clave: string; nombre: string; cantidad: number; porcentaje: number }[];
    };
    ultimos_meses: Tramo[];
    tendencia: Tramo[];
    ranking: { punto: Zona[]; barrio: Zona[] };
    franja_horaria: Hora[];
    /** hh:mm:ss */
    actualizado: string;
}

interface Filtros {
    periodo?: string;
    desde?: string;
    hasta?: string;
    categorias?: string[];
    granularidad?: string;
}

interface Props {
    filtros: Filtros;
    analisis: Analisis;
    categorias: Opciones;
    /** Segundos entre actualizaciones automáticas (US-022). */
    intervaloRefresco: number;
}

type VistaRanking = 'punto' | 'barrio';

// Un solo tono para los datos: la categoría siempre va escrita (título del
// mini gráfico, eje o tabla), nunca se identifica solo por color.
const CIAN = '#00D2FF';
const CIAN_APAGADO = '#0e7fa8';
const TINTA = { principal: '#E2E8F0', secundaria: '#94A3B8', grilla: '#1E3350' };

const PERIODOS: [string, string][] = [
    ['mes', 'Último mes'],
    ['trimestre', 'Últimos 3 meses'],
    ['anio', 'Último año'],
    ['personalizado', 'Personalizado'],
];

const GRANULARIDADES: [string, string][] = [
    ['dia', 'Día'],
    ['semana', 'Semana'],
    ['mes', 'Mes'],
];

const ejeProps = {
    stroke: TINTA.grilla,
    tick: { fill: TINTA.secundaria, fontSize: 11 },
    tickLine: false,
};

const conValor = (o: Partial<Filtros>): Partial<Filtros> =>
    Object.fromEntries(
        Object.entries(o).filter(([, v]) => (Array.isArray(v) ? v.length > 0 : v !== '' && v != null)),
    );

function Panel({
    titulo,
    nota,
    acciones,
    children,
    className = '',
}: {
    titulo: ReactNode;
    nota?: ReactNode;
    acciones?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`border border-atalaya-border bg-atalaya-surface p-5 ${className}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                        {titulo}
                    </h3>
                    {nota && <p className="mt-1 text-xs text-atalaya-text-dim">{nota}</p>}
                </div>
                {acciones}
            </div>
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Segmentado<T extends string>({
    opciones,
    valor,
    onChange,
}: {
    opciones: [T, string][];
    valor: T;
    onChange: (valor: T) => void;
}) {
    return (
        <div className="flex">
            {opciones.map(([clave, etiqueta]) => (
                <button
                    key={clave}
                    type="button"
                    aria-pressed={valor === clave}
                    onClick={() => onChange(clave)}
                    className={
                        'border px-2.5 py-1 font-mono text-[10px] font-bold uppercase ' +
                        (valor === clave
                            ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                            : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                    }
                >
                    {etiqueta}
                </button>
            ))}
        </div>
    );
}

function Variacion({ valor }: { valor: number | null | undefined }) {
    if (valor === null || valor === undefined) {
        return <span className="text-atalaya-text-dim">sin datos del período anterior</span>;
    }

    const sube = valor > 0;

    return (
        <span className={sube ? 'text-atalaya-amber' : valor < 0 ? 'text-emerald-400' : 'text-atalaya-text-muted'}>
            {sube ? '▲' : valor < 0 ? '▼' : '='} {Math.abs(valor)}%
        </span>
    );
}

function TooltipOscuro({ active, payload, label, sufijo = '' }: TooltipProps<ValueType, NameType> & { sufijo?: string }) {
    if (!active || !payload?.length) {
        return null;
    }

    const fila = payload[0].payload;

    return (
        <div className="border border-atalaya-border bg-atalaya-canvas px-3 py-2 text-xs shadow-lg">
            <p className="font-mono font-bold text-white">{label ?? fila.etiqueta}</p>
            <p className="text-atalaya-text-muted">
                {payload[0].value} novedad(es)
                {fila.porcentaje !== undefined && ` · ${fila.porcentaje}%`}
                {sufijo}
            </p>
            <p className="mt-1 text-[10px] text-atalaya-cyan">Clic para ver las novedades</p>
        </div>
    );
}

export default function Index({ filtros, analisis: a, categorias, intervaloRefresco }: Props) {
    const [actualizando, setActualizando] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [vistaRanking, setVistaRanking] = useState<VistaRanking>('punto');
    const [desglosar, setDesglosar] = useState(false);
    const [rango, setRango] = useState({ desde: filtros.desde ?? a.periodo.desde, hasta: filtros.hasta ?? a.periodo.hasta });

    const periodo = filtros.periodo ?? 'mes';
    const seleccionadas = filtros.categorias ?? [];

    const filtrar = (cambios: Partial<Filtros>) =>
        router.get(route('analisis.index'), conValor({ ...filtros, ...cambios }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    // US-022: actualización automática y manual, solo de los datos.
    const actualizar = useCallback(() => {
        // Inertia v2 no tiene onException por visita: un error de red solo
        // dispara el evento global `exception`. Se escucha mientras dura esta
        // actualización; preventDefault evita el error sin capturar en consola.
        const dejarDeEscuchar = router.on('exception', (evento) => {
            evento.preventDefault();
            setError('Sin conexión con el servidor. Se reintentará en el próximo ciclo.');
        });

        router.reload({
            // reload() ya preserva scroll y estado.
            only: ['analisis'],
            onStart: () => setActualizando(true),
            onFinish: () => {
                setActualizando(false);
                dejarDeEscuchar();
            },
            // Cancelada o interrumpida por otra visita: Inertia no llama a onFinish.
            onCancel: dejarDeEscuchar,
            onSuccess: () => setError(null),
            onError: () => setError('No se pudieron actualizar los datos. Se reintentará en el próximo ciclo.'),
        });
    }, []);

    useEffect(() => {
        const id = setInterval(() => {
            if (!document.hidden) {
                actualizar();
            }
        }, intervaloRefresco * 1000);

        return () => clearInterval(id);
    }, [actualizar, intervaloRefresco]);

    // US-021: drill-down a la consulta de eventos manteniendo los filtros.
    const verEventos = (params: Record<string, string | string[]>) =>
        router.visit(
            route('eventos.index', {
                desde: a.periodo.desde,
                hasta: a.periodo.hasta,
                categorias: seleccionadas,
                ...params,
                origen: 'analisis',
            }),
        );

    const alternarCategoria = (clave: string) =>
        filtrar({
            categorias: seleccionadas.includes(clave)
                ? seleccionadas.filter((c) => c !== clave)
                : [...seleccionadas, clave],
        });

    const hoy = new Date().toLocaleDateString('en-CA');
    const inicioMes = hoy.slice(0, 8) + '01';
    const inicioAnio = hoy.slice(0, 5) + '01-01';
    const ranking = a.ranking[vistaRanking];
    const maxRanking = ranking[0]?.cantidad ?? 0;
    const clicEnBarra =
        <T,>(fn: (fila: T) => void) =>
        (estado: { activePayload?: { payload: T }[] } | null) =>
            estado?.activePayload?.[0] && fn(estado.activePayload[0].payload);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Análisis
                </h2>
            }
        >
            <Head title="Análisis" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {/* Filtros en una fila, arriba de todo. */}
                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div>
                            <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Período</p>
                            <Segmentado
                                opciones={PERIODOS}
                                valor={periodo}
                                onChange={(p) =>
                                    p === 'personalizado'
                                        ? filtrar({ periodo: p, desde: rango.desde, hasta: rango.hasta })
                                        : filtrar({ periodo: p, desde: '', hasta: '', granularidad: '' })
                                }
                            />
                        </div>
                        {periodo === 'personalizado' && (
                            <div className="flex items-end gap-2">
                                <FechaInput

                                    value={rango.desde}
                                    max={hoy}
                                    onChange={(e) => setRango((r) => ({ ...r, desde: e.target.value }))}
                                    onBlur={() => filtrar({ desde: rango.desde, hasta: rango.hasta })}
                                />
                                <FechaInput

                                    value={rango.hasta}
                                    max={hoy}
                                    onChange={(e) => setRango((r) => ({ ...r, hasta: e.target.value }))}
                                    onBlur={() => filtrar({ desde: rango.desde, hasta: rango.hasta })}
                                />
                            </div>
                        )}
                        <div>
                            <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Categoría</p>
                            <div className="flex flex-wrap gap-1">
                                {Object.entries(categorias).map(([clave, nombre]) => (
                                    <button
                                        key={clave}
                                        type="button"
                                        aria-pressed={seleccionadas.includes(clave)}
                                        onClick={() => alternarCategoria(clave)}
                                        className={
                                            'border px-2.5 py-1 font-mono text-[10px] font-bold uppercase ' +
                                            (seleccionadas.includes(clave)
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                        }
                                    >
                                        {nombre}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="ml-auto flex items-center gap-3 font-mono text-xs text-atalaya-text-dim">
                            <span className="flex items-center gap-2" aria-live="polite">
                                <span
                                    className={`inline-block h-2 w-2 rounded-full ${actualizando ? 'animate-pulse bg-atalaya-cyan' : error ? 'bg-atalaya-crimson' : 'bg-emerald-400'}`}
                                />
                                {actualizando ? 'Actualizando…' : `Actualizado ${a.actualizado}`}
                            </span>
                            <button
                                type="button"
                                onClick={actualizar}
                                disabled={actualizando}
                                className="border border-atalaya-border px-2.5 py-1 font-bold uppercase text-atalaya-cyan hover:bg-atalaya-cyan/10 disabled:opacity-50"
                            >
                                Actualizar ahora
                            </button>
                        </div>
                    </div>

                    {error && (
                        <p role="alert" className="border border-atalaya-crimson/50 bg-atalaya-crimson/10 px-4 py-2 text-sm text-red-100">
                            {error}
                        </p>
                    )}

                    {/* US-015: KPIs (clic = desglose en la consulta de eventos). */}
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {([
                            ['Novedades del mes', a.kpis.mes, 'vs. mismo tramo del mes anterior', { desde: inicioMes, hasta: hoy }],
                            ['Novedades del año', a.kpis.anio, 'vs. mismo tramo del año anterior', { desde: inicioAnio, hasta: hoy }],
                        ] as const).map(([titulo, k, nota, rangoKpi]) => (
                            <button
                                key={titulo}
                                type="button"
                                onClick={() => verEventos(rangoKpi)}
                                className="border border-atalaya-border bg-atalaya-surface p-5 text-left hover:border-atalaya-cyan"
                            >
                                <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">{titulo}</p>
                                <p className="mt-2 font-mono text-3xl font-bold text-white">{k.total}</p>
                                <p className="mt-1 text-xs">
                                    <Variacion valor={k.variacion} /> <span className="text-atalaya-text-dim">{nota} ({k.anterior})</span>
                                </p>
                            </button>
                        ))}
                        <div className="border border-atalaya-border bg-atalaya-surface p-5">
                            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Período elegido · {a.kpis.periodo} novedades
                            </p>
                            <ul className="mt-2 space-y-1.5">
                                {a.kpis.por_categoria.map((c) => (
                                    <li key={c.clave}>
                                        <button
                                            type="button"
                                            onClick={() => verEventos({ categorias: [c.clave] })}
                                            className="flex w-full items-center justify-between gap-3 text-sm hover:text-atalaya-cyan"
                                        >
                                            <span className="text-atalaya-text-muted">{c.nombre}</span>
                                            <span className="font-mono text-white">
                                                {c.porcentaje}% <span className="text-atalaya-text-dim">({c.cantidad})</span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    {/* US-015: últimos 6 meses por categoría (pequeños múltiplos). */}
                    <Panel titulo="Novedades por categoría · últimos 6 meses" nota="Un gráfico por categoría. Clic en una barra para ver esas novedades.">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {Object.entries(categorias).map(([clave, nombre]) => (
                                <div key={clave}>
                                    <p className="mb-1 text-sm text-white">{nombre}</p>
                                    <ResponsiveContainer width="100%" height={140}>
                                        <BarChart
                                            data={a.ultimos_meses}
                                            margin={{ top: 16, right: 4, left: -28 }}
                                            onClick={clicEnBarra((m: Tramo) => verEventos({ desde: m.desde, hasta: m.hasta, categorias: [clave] }))}
                                        >
                                            <CartesianGrid vertical={false} stroke={TINTA.grilla} />
                                            <XAxis dataKey="etiqueta" {...ejeProps} tickFormatter={(v: string) => v.slice(0, 3)} />
                                            <YAxis allowDecimals={false} {...ejeProps} axisLine={false} />
                                            <Tooltip cursor={{ fill: '#ffffff08' }} content={<TooltipOscuro sufijo={` · ${nombre}`} />} />
                                            <Bar dataKey={clave} fill={CIAN} maxBarSize={22} radius={[4, 4, 0, 0]} className="cursor-pointer">
                                                <LabelList dataKey={clave} position="top" fill={TINTA.secundaria} fontSize={10} formatter={(v: number) => (v ? v : '')} />
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    {/* US-018: tendencias. */}
                    <Panel
                        titulo="Tendencia temporal"
                        nota={`${a.periodo.desde.split('-').reverse().join('/')} al ${a.periodo.hasta.split('-').reverse().join('/')} · clic en un punto para ver las novedades de ese tramo.`}
                        acciones={
                            <div className="flex flex-wrap items-center gap-3">
                                <Segmentado opciones={GRANULARIDADES} valor={a.granularidad} onChange={(g) => filtrar({ granularidad: g })} />
                                <label className="flex items-center gap-2 text-xs text-atalaya-text-muted">
                                    <input
                                        type="checkbox"
                                        checked={desglosar}
                                        onChange={(e) => setDesglosar(e.target.checked)}
                                        className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan"
                                    />
                                    Desglosar por categoría
                                </label>
                            </div>
                        }
                    >
                        <div className={desglosar ? 'grid grid-cols-1 gap-4 md:grid-cols-3' : ''}>
                            {(desglosar ? Object.entries(categorias) : [['total', 'Total']]).map(([clave, nombre]) => (
                                <div key={clave}>
                                    {desglosar && <p className="mb-1 text-sm text-white">{nombre}</p>}
                                    <ResponsiveContainer width="100%" height={desglosar ? 160 : 240}>
                                        <LineChart
                                            data={a.tendencia}
                                            margin={{ top: 8, right: 28, left: -20 }}
                                            onClick={clicEnBarra((t: Tramo) =>
                                                verEventos({ desde: t.desde, hasta: t.hasta, ...(clave !== 'total' && { categorias: [clave] }) }),
                                            )}
                                        >
                                            <CartesianGrid vertical={false} stroke={TINTA.grilla} />
                                            <XAxis dataKey="etiqueta" {...ejeProps} minTickGap={16} />
                                            <YAxis allowDecimals={false} {...ejeProps} axisLine={false} />
                                            <Tooltip
                                                cursor={{ stroke: TINTA.secundaria, strokeDasharray: '3 3' }}
                                                content={<TooltipOscuro sufijo={clave !== 'total' ? ` · ${nombre}` : ''} />}
                                            />
                                            <Line
                                                type="linear"
                                                dataKey={clave}
                                                stroke={CIAN}
                                                strokeWidth={2}
                                                dot={a.tendencia.length <= 31 ? { r: 3, fill: CIAN } : false}
                                                activeDot={{ r: 6, stroke: '#0B1C30', strokeWidth: 2, className: 'cursor-pointer' }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            ))}
                        </div>
                    </Panel>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {/* US-019: ranking de zonas críticas. */}
                        <Panel
                            titulo="Zonas críticas · top 10"
                            nota="Del período elegido. No incluye novedades sin ubicación."
                            acciones={
                                <Segmentado
                                    opciones={[
                                        ['punto', 'Por PM'],
                                        ['barrio', 'Por barrio'],
                                    ] satisfies [VistaRanking, string][]}
                                    valor={vistaRanking}
                                    onChange={setVistaRanking}
                                />
                            }
                        >
                            {ranking.length === 0 ? (
                                <p className="text-sm text-atalaya-text-dim">
                                    {vistaRanking === 'barrio'
                                        ? 'Sin datos: los PMs con novedades todavía no tienen barrio cargado (Puntos → editar).'
                                        : 'Sin novedades con ubicación en el período.'}
                                </p>
                            ) : (
                                <ol className="space-y-2.5">
                                    {ranking.map((r, i) => (
                                        <li key={r.clave}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    verEventos(vistaRanking === 'barrio' ? { barrio_id: r.clave } : { punto_monitoreo_id: r.clave })
                                                }
                                                className="w-full text-left hover:text-atalaya-cyan"
                                            >
                                                <div className="flex justify-between gap-4 text-sm">
                                                    <span className="text-white">
                                                        <span className="mr-2 font-mono text-atalaya-text-dim">{i + 1}.</span>
                                                        {r.nombre}
                                                    </span>
                                                    <span className="whitespace-nowrap font-mono text-atalaya-text-muted">
                                                        {r.cantidad} · {r.porcentaje}%
                                                    </span>
                                                </div>
                                                <div className="mt-1 h-1.5 bg-atalaya-canvas">
                                                    <div
                                                        className="h-full rounded-r bg-atalaya-cyan"
                                                        style={{ width: `${(r.cantidad / maxRanking) * 100}%` }}
                                                    />
                                                </div>
                                            </button>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Panel>

                        {/* US-020: franja horaria. */}
                        <Panel
                            titulo="Distribución por hora del día"
                            nota="Resaltadas las 3 horas de mayor incidencia. Clic en una barra para ver esas novedades."
                        >
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart
                                    data={a.franja_horaria}
                                    margin={{ top: 20, right: 4, left: -24 }}
                                    onClick={clicEnBarra((h: Hora) =>
                                        verEventos({ hora_desde: `${h.etiqueta}:00`, hora_hasta: `${h.etiqueta}:59` }),
                                    )}
                                >
                                    <CartesianGrid vertical={false} stroke={TINTA.grilla} />
                                    <XAxis dataKey="etiqueta" {...ejeProps} interval={1} />
                                    <YAxis allowDecimals={false} {...ejeProps} axisLine={false} />
                                    <Tooltip
                                        cursor={{ fill: '#ffffff08' }}
                                        content={({ active, payload }) => (
                                            <TooltipOscuro
                                                active={active}
                                                payload={payload}
                                                label={payload?.[0] ? `${payload[0].payload.etiqueta}:00 a ${payload[0].payload.etiqueta}:59 hs` : ''}
                                            />
                                        )}
                                    />
                                    <Bar dataKey="cantidad" maxBarSize={16} radius={[4, 4, 0, 0]} className="cursor-pointer">
                                        {a.franja_horaria.map((h) => (
                                            <Cell key={h.hora} fill={h.destacada ? CIAN : CIAN_APAGADO} />
                                        ))}
                                        <LabelList
                                            dataKey="cantidad"
                                            position="top"
                                            fontSize={10}
                                            fill={TINTA.principal}
                                            content={({ x, y, width, index = -1 }) =>
                                                a.franja_horaria[index]?.destacada ? (
                                                    <text x={Number(x) + Number(width) / 2} y={Number(y) - 6} textAnchor="middle" fill={TINTA.principal} fontSize={10}>
                                                        {a.franja_horaria[index].porcentaje}%
                                                    </text>
                                                ) : null
                                            }
                                        />
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </Panel>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
