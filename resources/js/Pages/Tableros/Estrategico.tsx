import { colorGrupo } from '@/catalogos';
import FechaInput from '@/Components/FechaInput';
import { Delta, ejeProps, PestanasTableros, Tarjeta, TINTA } from '@/Components/Tableros/Encabezado';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Opciones } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface Comparacion {
    desde: string;
    hasta: string;
    total: number;
    variacion: number | null;
}

interface PuntoTop {
    id: number;
    codigo: string;
    nombre: string;
    cantidad: number;
    porcentaje: number;
}

type Dia = { fecha: string; etiqueta: string; total: number } & Record<string, number | string>;

/** TableroEstrategicoService::calcular. */
interface Tablero {
    periodo: { desde: string; hasta: string };
    variacion: { total: number; dias: number; anterior: Comparacion; interanual: Comparacion };
    perfil_temporal: { dias: string[]; celdas: number[][]; maximo: number };
    top_puntos: PuntoTop[];
    tendencia: Dia[];
    actualizado: string;
}

interface Filtros {
    desde?: string;
    hasta?: string;
    categorias?: string[];
}

interface Props {
    filtros: Filtros;
    tablero: Tablero;
    categorias: Opciones;
}

const dmy = (iso: string) => iso.split('-').reverse().join('/');
const iso = (d: Date) => d.toLocaleDateString('en-CA');

/** Rangos rápidos, calculados en el navegador (hora local). */
const presets = (): [string, string, string][] => {
    const hoy = new Date();
    const a = hoy.getFullYear();
    const m = hoy.getMonth();

    return [
        ['Mes en curso', iso(new Date(a, m, 1)), iso(hoy)],
        ['Mes anterior', iso(new Date(a, m - 1, 1)), iso(new Date(a, m, 0))],
        ['Últimos 90 días', iso(new Date(a, m, hoy.getDate() - 89)), iso(hoy)],
        ['Año en curso', iso(new Date(a, 0, 1)), iso(hoy)],
        ['Año anterior', iso(new Date(a - 1, 0, 1)), iso(new Date(a - 1, 11, 31))],
    ];
};

const conValor = (o: Filtros): Record<string, string | string[]> =>
    Object.fromEntries(Object.entries(o).filter(([, v]) => (Array.isArray(v) ? v.length > 0 : v !== '' && v != null)));

/** Celda del heatmap: opacidad del cian proporcional a la cantidad. */
const fondoCelda = (cantidad: number, maximo: number) =>
    cantidad === 0 || maximo === 0 ? '#0F2847' : `rgba(0, 210, 255, ${0.15 + 0.85 * (cantidad / maximo)})`;

export default function Estrategico({ filtros, tablero: t, categorias }: Props) {
    const [rango, setRango] = useState({ desde: t.periodo.desde, hasta: t.periodo.hasta });
    const seleccionadas = filtros.categorias ?? [];
    const hoy = iso(new Date());

    const filtrar = (cambios: Filtros) =>
        router.get(route('tableros.estrategico'), conValor({ ...filtros, ...cambios }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const aplicarRango = (desde: string, hasta: string) => {
        setRango({ desde, hasta });
        if (desde && hasta && desde <= hasta) {
            filtrar({ desde, hasta });
        }
    };

    const alternarCategoria = (clave: string) =>
        filtrar({
            categorias: seleccionadas.includes(clave) ? seleccionadas.filter((c) => c !== clave) : [...seleccionadas, clave],
        });

    // Drill-down a la consulta de novedades con el rango y las categorías del tablero.
    const verEventos = (params: Record<string, string | number | string[]>) =>
        router.visit(
            route('eventos.index', {
                desde: t.periodo.desde,
                hasta: t.periodo.hasta,
                categorias: seleccionadas,
                ...params,
                origen: 'tableros',
            }),
        );

    const lineas = Object.entries(categorias).filter(([clave]) => seleccionadas.length === 0 || seleccionadas.includes(clave));
    const v = t.variacion;
    const p = t.perfil_temporal;

    return (
        <AuthenticatedLayout
            header={<h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">Tableros</h2>}
        >
            <Head title="Análisis estratégico" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <PestanasTableros
                        extra={<span className="font-mono text-xs text-atalaya-text-dim">Calculado {t.actualizado}</span>}
                    />

                    {/* Filtros: rango y grupos de evento. */}
                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div>
                            <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Período</p>
                            <div className="flex flex-wrap gap-1">
                                {presets().map(([etiqueta, desde, hasta]) => {
                                    const activo = t.periodo.desde === desde && t.periodo.hasta === hasta;

                                    return (
                                        <button
                                            key={etiqueta}
                                            type="button"
                                            aria-pressed={activo}
                                            onClick={() => aplicarRango(desde, hasta)}
                                            className={
                                                'border px-2.5 py-1 font-mono text-[10px] font-bold uppercase ' +
                                                (activo
                                                    ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                    : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                            }
                                        >
                                            {etiqueta}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                        <div className="flex items-end gap-2">
                            <div>
                                <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Desde</p>
                                <FechaInput
                                    value={rango.desde}
                                    max={hoy}
                                    onChange={(e) => setRango((r) => ({ ...r, desde: e.target.value }))}
                                    onBlur={() => aplicarRango(rango.desde, rango.hasta)}
                                />
                            </div>
                            <div>
                                <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Hasta</p>
                                <FechaInput
                                    value={rango.hasta}
                                    max={hoy}
                                    onChange={(e) => setRango((r) => ({ ...r, hasta: e.target.value }))}
                                    onBlur={() => aplicarRango(rango.desde, rango.hasta)}
                                />
                            </div>
                        </div>
                        <div>
                            <p className="mb-1 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Grupo</p>
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
                    </div>

                    {/* KPI 1: variación intermensual / interanual. */}
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <button
                            type="button"
                            onClick={() => verEventos({})}
                            className="border border-atalaya-border bg-atalaya-surface p-5 text-left hover:border-atalaya-cyan"
                        >
                            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">Novedades del período</p>
                            <p className="mt-2 font-mono text-4xl font-bold text-white">{v.total}</p>
                            <p className="mt-1 text-xs text-atalaya-text-dim">
                                {dmy(t.periodo.desde)} al {dmy(t.periodo.hasta)} · {v.dias} días
                            </p>
                        </button>
                        {(
                            [
                                ['Vs. período anterior', v.anterior],
                                ['Vs. mismo período del año anterior', v.interanual],
                            ] as const
                        ).map(([titulo, c]) => (
                            <button
                                key={titulo}
                                type="button"
                                onClick={() => verEventos({ desde: c.desde, hasta: c.hasta })}
                                className="border border-atalaya-border bg-atalaya-surface p-5 text-left hover:border-atalaya-cyan"
                            >
                                <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">{titulo}</p>
                                <p className="mt-2 text-3xl">
                                    <Delta valor={c.variacion} />
                                </p>
                                <p className="mt-1 text-xs text-atalaya-text-dim">
                                    {c.total} novedades del {dmy(c.desde)} al {dmy(c.hasta)}
                                </p>
                            </button>
                        ))}
                    </div>

                    {/* Línea de tendencia diaria por grupo. */}
                    <Tarjeta titulo="Tendencia diaria por grupo" nota="Clic en un día para ver sus novedades.">
                        <ResponsiveContainer width="100%" height={280}>
                            <LineChart
                                data={t.tendencia}
                                margin={{ top: 8, right: 24, left: -20 }}
                                onClick={(e) => {
                                    const dia = e?.activePayload?.[0]?.payload as Dia | undefined;
                                    if (dia) {
                                        verEventos({ desde: dia.fecha, hasta: dia.fecha });
                                    }
                                }}
                            >
                                <CartesianGrid vertical={false} stroke={TINTA.grilla} />
                                <XAxis dataKey="etiqueta" {...ejeProps} minTickGap={16} />
                                <YAxis allowDecimals={false} {...ejeProps} axisLine={false} />
                                <Tooltip
                                    cursor={{ stroke: TINTA.secundaria, strokeDasharray: '3 3' }}
                                    contentStyle={{ background: '#031427', border: '1px solid #1E3A5F', fontSize: 12 }}
                                    labelStyle={{ color: '#FFFFFF', fontWeight: 700 }}
                                />
                                <Legend wrapperStyle={{ fontSize: 12, color: TINTA.secundaria }} />
                                {lineas.map(([clave, nombre]) => (
                                    <Line
                                        key={clave}
                                        type="linear"
                                        dataKey={clave}
                                        name={nombre}
                                        stroke={colorGrupo(clave)}
                                        strokeWidth={2}
                                        dot={t.tendencia.length <= 31 ? { r: 2.5, fill: colorGrupo(clave) } : false}
                                        activeDot={{ r: 5, className: 'cursor-pointer' }}
                                        isAnimationActive={false}
                                    />
                                ))}
                            </LineChart>
                        </ResponsiveContainer>
                    </Tarjeta>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
                        {/* KPI 2: perfil temporal (día de la semana × hora). */}
                        <Tarjeta
                            titulo="Perfil temporal · día × hora"
                            nota="Más intenso = más novedades. Pasá el mouse para ver la cantidad."
                            className="lg:col-span-3"
                        >
                            <div className="overflow-x-auto">
                                <table className="w-full border-separate border-spacing-[2px] font-mono text-[10px]">
                                    <thead>
                                        <tr>
                                            <th />
                                            {Array.from({ length: 24 }, (_, h) => (
                                                <th key={h} className="font-normal text-atalaya-text-dim">
                                                    {h % 2 === 0 ? String(h).padStart(2, '0') : ''}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {p.dias.map((dia, d) => (
                                            <tr key={dia}>
                                                <th className="pr-2 text-left font-normal text-atalaya-text-muted">{dia}</th>
                                                {p.celdas[d].map((cantidad, h) => (
                                                    <td
                                                        key={h}
                                                        title={`${dia} ${String(h).padStart(2, '0')}:00–${String(h).padStart(2, '0')}:59 · ${cantidad} novedad(es)`}
                                                        className="h-6 min-w-[18px]"
                                                        style={{ backgroundColor: fondoCelda(cantidad, p.maximo) }}
                                                    />
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-3 flex items-center gap-2 font-mono text-[10px] text-atalaya-text-dim">
                                0
                                <span
                                    className="inline-block h-2 w-32"
                                    style={{ background: 'linear-gradient(to right, rgba(0,210,255,0.15), rgba(0,210,255,1))' }}
                                />
                                {p.maximo}
                            </div>
                        </Tarjeta>

                        {/* KPI 3: top 5 PMs. */}
                        <Tarjeta
                            titulo="Top 5 puntos de monitoreo"
                            nota="Clic en una barra para ver las novedades de ese PM."
                            className="lg:col-span-2"
                        >
                            {t.top_puntos.length === 0 ? (
                                <p className="text-sm text-atalaya-text-dim">Sin novedades con PM en el período.</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={t.top_puntos.length * 44 + 20}>
                                    <BarChart
                                        data={t.top_puntos}
                                        layout="vertical"
                                        margin={{ top: 0, right: 48, left: 0, bottom: 0 }}
                                        onClick={(e) => {
                                            const pm = e?.activePayload?.[0]?.payload as PuntoTop | undefined;
                                            if (pm) {
                                                verEventos({ punto_monitoreo_id: pm.id });
                                            }
                                        }}
                                    >
                                        <XAxis type="number" hide allowDecimals={false} />
                                        <YAxis type="category" dataKey="codigo" width={70} {...ejeProps} axisLine={false} />
                                        <Tooltip
                                            cursor={{ fill: '#ffffff08' }}
                                            content={({ active, payload }) => {
                                                const pm = payload?.[0]?.payload as PuntoTop | undefined;

                                                return active && pm ? (
                                                    <div className="border border-atalaya-border bg-atalaya-canvas px-3 py-2 text-xs">
                                                        <p className="font-bold text-white">
                                                            {pm.codigo} — {pm.nombre}
                                                        </p>
                                                        <p className="text-atalaya-text-muted">
                                                            {pm.cantidad} novedad(es) · {pm.porcentaje}% de las ubicadas
                                                        </p>
                                                        <p className="mt-1 text-[10px] text-atalaya-cyan">Clic para ver las novedades</p>
                                                    </div>
                                                ) : null;
                                            }}
                                        />
                                        <Bar dataKey="cantidad" fill="#00D2FF" barSize={22} radius={[0, 4, 4, 0]} className="cursor-pointer">
                                            <LabelList dataKey="cantidad" position="right" fill={TINTA.principal} fontSize={11} />
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </Tarjeta>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
