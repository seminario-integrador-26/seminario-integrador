import InputLabel from '@/Components/InputLabel';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { COLOR_GRUPO as COLOR_CATEGORIA } from '@/catalogos';
import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
    type TooltipProps,
} from 'recharts';

/** Cantidad por categoría o por tipo de PM (clave = código del catálogo). */
interface Conteo {
    clave: string;
    nombre: string;
    cantidad: number;
}

/** Tramo de la evolución: total y cantidad por código de categoría. */
type Tramo = { clave: string; etiqueta: string; total: number } & Record<string, string | number>;

/** CalculadorEstadistica::calcular + período (EstadisticaService). */
interface Estadisticas {
    periodo: { titulo: string; desde: string; hasta: string };
    total: number;
    sin_ubicacion: number;
    con_ubicacion: number;
    no_cuantificables: number;
    por_categoria: Conteo[];
    por_tipo: { nombre: string; categoria: string; cantidad: number }[];
    por_tipo_punto: Conteo[];
    por_punto: {
        puntos_con_eventos: number;
        ranking: { id: number; codigo: string; nombre: string; tipo_punto: string; cantidad: number }[];
    };
    evolucion: Tramo[];
}

interface Filtros {
    periodo: 'mensual' | 'anual';
    anio: number;
    mes: number;
}

// Colores por grupo de evento (@/catalogos). El grupo SIEMPRE va escrito en
// el eje y en la tabla: el color nunca es la única pista.

const TINTA = {
    principal: '#E2E8F0',
    secundaria: '#94A3B8',
    grilla: '#1E3350',
};

const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

const porcentaje = (parte: number, total: number) =>
    total > 0 ? `${Math.round((parte / total) * 1000) / 10}%` : '—';

function Panel({
    titulo,
    nota,
    children,
    className = '',
}: {
    titulo: ReactNode;
    nota?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`border border-atalaya-border bg-atalaya-surface p-6 ${className}`}
        >
            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                {titulo}
            </h3>
            {nota && (
                <p className="mt-1 text-xs text-atalaya-text-dim">{nota}</p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Indicador({ etiqueta, valor, detalle }: { etiqueta: string; valor: number; detalle?: string }) {
    return (
        <div className="border border-atalaya-border bg-atalaya-surface p-5">
            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                {etiqueta}
            </p>
            <p className="mt-2 font-mono text-3xl font-bold text-white">
                {valor}
            </p>
            {detalle && (
                <p className="mt-1 text-xs text-atalaya-text-muted">{detalle}</p>
            )}
        </div>
    );
}

function TooltipOscuro({ active, payload, label, desglose }: TooltipProps<number, string> & { desglose?: Conteo[] }) {
    if (!active || !payload?.length) {
        return null;
    }

    const fila = payload[0].payload;

    return (
        <div className="border border-atalaya-border bg-atalaya-canvas px-3 py-2 text-xs shadow-lg">
            <p className="font-mono font-bold text-white">
                {label ?? fila.nombre}
            </p>
            <p className="text-atalaya-text-muted">
                {payload[0].value} novedad(es)
            </p>
            {desglose?.map(({ clave, nombre }) =>
                fila[clave] > 0 ? (
                    <p key={clave} className="text-atalaya-text-muted">
                        <span
                            className="mr-1 inline-block h-2 w-2"
                            style={{ background: COLOR_CATEGORIA[clave] }}
                        />
                        {nombre}: {fila[clave]}
                    </p>
                ) : null,
            )}
        </div>
    );
}

const ejeProps = {
    stroke: TINTA.grilla,
    tick: { fill: TINTA.secundaria, fontSize: 11 },
    tickLine: false,
};

export default function Index({ filtros, estadisticas: e }: { filtros: Filtros; estadisticas: Estadisticas }) {
    const anioActual = new Date().getFullYear();
    const anios = Array.from({ length: 6 }, (_, i) => anioActual - i);

    const cambiar = (campo: keyof Filtros) => (ev: { target: { value: string } }) =>
        router.get(
            route('estadisticas.index'),
            { ...filtros, [campo]: ev.target.value },
            { preserveScroll: true, replace: true },
        );

    const maxPunto = e.por_punto.ranking[0]?.cantidad ?? 0;
    const conUbicacion = e.con_ubicacion;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Estadísticas
                </h2>
            }
        >
            <Head title="Estadísticas" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {/* Selector de período, en una sola fila arriba de los gráficos. */}
                    <div className="flex flex-wrap items-end gap-4 border border-atalaya-border bg-atalaya-surface p-4">
                        <div>
                            <InputLabel value="Período" />
                            <div className="mt-1 flex">
                                {['mensual', 'anual'].map((p) => (
                                    <button
                                        key={p}
                                        type="button"
                                        aria-pressed={filtros.periodo === p}
                                        onClick={() =>
                                            cambiar('periodo')({
                                                target: { value: p },
                                            })
                                        }
                                        className={
                                            'border px-3 py-1.5 font-mono text-xs font-bold uppercase ' +
                                            (filtros.periodo === p
                                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                                : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                        }
                                    >
                                        {p}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {filtros.periodo === 'mensual' && (
                            <div className="w-40">
                                <InputLabel htmlFor="mes" value="Mes" />
                                <Select
                                    id="mes"
                                    className="mt-1"
                                    value={filtros.mes}
                                    onChange={cambiar('mes')}
                                >
                                    {MESES.map((nombre, i) => (
                                        <option key={nombre} value={i + 1}>
                                            {nombre}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}

                        <div className="w-28">
                            <InputLabel htmlFor="anio" value="Año" />
                            <Select
                                id="anio"
                                className="mt-1"
                                value={filtros.anio}
                                onChange={cambiar('anio')}
                            >
                                {anios.map((a) => (
                                    <option key={a} value={a}>
                                        {a}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <p className="ml-auto font-mono text-sm text-white">
                            {e.periodo.titulo}
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Indicador etiqueta="Novedades" valor={e.total} />
                        <Indicador
                            etiqueta="Con ubicación"
                            valor={conUbicacion}
                            detalle={porcentaje(conUbicacion, e.total)}
                        />
                        <Indicador
                            etiqueta="Sin ubicación"
                            valor={e.sin_ubicacion}
                            detalle="Fuera de la distribución por ubicación"
                        />
                        <Indicador
                            etiqueta="No cuantificables"
                            valor={e.no_cuantificables}
                            detalle="Tipos que no suman al total (RN4)"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Panel titulo="Por categoría">
                            <ResponsiveContainer
                                width="100%"
                                height={44 * e.por_categoria.length + 20}
                            >
                                <BarChart
                                    data={e.por_categoria}
                                    layout="vertical"
                                    margin={{ left: 8, right: 48 }}
                                >
                                    <XAxis type="number" hide allowDecimals={false} />
                                    <YAxis
                                        type="category"
                                        dataKey="nombre"
                                        width={130}
                                        {...ejeProps}
                                        tick={{ fill: TINTA.principal, fontSize: 12 }}
                                    />
                                    <Tooltip
                                        cursor={{ fill: '#ffffff08' }}
                                        content={<TooltipOscuro />}
                                    />
                                    <Bar
                                        dataKey="cantidad"
                                        barSize={18}
                                        radius={[0, 4, 4, 0]}
                                    >
                                        {e.por_categoria.map((c) => (
                                            <Cell
                                                key={c.clave}
                                                fill={COLOR_CATEGORIA[c.clave]}
                                            />
                                        ))}
                                        <LabelList
                                            dataKey="cantidad"
                                            position="right"
                                            fill={TINTA.principal}
                                            fontSize={12}
                                            formatter={(v: number) =>
                                                `${v} · ${porcentaje(v, e.total)}`
                                            }
                                        />
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </Panel>

                        <Panel
                            titulo="Por tipo de PM"
                            nota="Solo novedades con ubicación (RN11)."
                        >
                            <div className="grid grid-cols-3 gap-4">
                                {e.por_tipo_punto.map((j) => (
                                    <div
                                        key={j.clave}
                                        className="border border-atalaya-border p-4"
                                    >
                                        <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                            PM {j.nombre}
                                        </p>
                                        <p className="mt-2 font-mono text-2xl font-bold text-white">
                                            {j.cantidad}
                                        </p>
                                        <p className="text-xs text-atalaya-text-muted">
                                            {porcentaje(j.cantidad, conUbicacion)}{' '}
                                            de las novedades con ubicación
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-4 text-xs text-atalaya-text-dim">
                                {e.por_punto.puntos_con_eventos} PM con al menos
                                una novedad en el período.
                            </p>
                        </Panel>
                    </div>

                    <Panel
                        titulo={
                            filtros.periodo === 'anual'
                                ? 'Evolución mes a mes'
                                : 'Evolución semana a semana'
                        }
                        nota="Pasá el cursor sobre una barra para ver el desglose por categoría."
                    >
                        <ResponsiveContainer width="100%" height={240}>
                            <BarChart
                                data={e.evolucion}
                                margin={{ top: 16, right: 8, left: -16 }}
                            >
                                <CartesianGrid
                                    vertical={false}
                                    stroke={TINTA.grilla}
                                />
                                <XAxis dataKey="etiqueta" {...ejeProps} />
                                <YAxis allowDecimals={false} {...ejeProps} axisLine={false} />
                                <Tooltip
                                    cursor={{ fill: '#ffffff08' }}
                                    content={
                                        <TooltipOscuro
                                            desglose={e.por_categoria}
                                        />
                                    }
                                />
                                <Bar
                                    dataKey="total"
                                    fill="#00D2FF"
                                    maxBarSize={36}
                                    radius={[4, 4, 0, 0]}
                                />
                            </BarChart>
                        </ResponsiveContainer>

                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-left font-mono text-[10px] font-bold uppercase tracking-wider text-atalaya-text-muted">
                                        <th className="py-2 pr-4">
                                            Período
                                        </th>
                                        {e.por_categoria.map((c) => (
                                            <th
                                                key={c.clave}
                                                className="py-2 pr-4 text-right"
                                            >
                                                {c.nombre}
                                            </th>
                                        ))}
                                        <th className="py-2 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-atalaya-border font-mono">
                                    {e.evolucion.map((t) => (
                                        <tr key={t.clave}>
                                            <td className="py-1.5 pr-4 text-atalaya-text-muted">
                                                {t.etiqueta}
                                            </td>
                                            {e.por_categoria.map((c) => (
                                                <td
                                                    key={c.clave}
                                                    className="py-1.5 pr-4 text-right text-atalaya-text-muted"
                                                >
                                                    {t[c.clave]}
                                                </td>
                                            ))}
                                            <td className="py-1.5 text-right text-white">
                                                {t.total}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Panel
                            titulo="Puntos de monitoreo con más novedades"
                            nota="Top 10 del período. No incluye novedades sin ubicación."
                        >
                            {e.por_punto.ranking.length === 0 ? (
                                <p className="text-sm text-atalaya-text-dim">
                                    Sin novedades con ubicación en el período.
                                </p>
                            ) : (
                                <ol className="space-y-3">
                                    {e.por_punto.ranking.map((p, i) => (
                                        <li key={p.id}>
                                            <div className="flex justify-between gap-4 text-sm">
                                                <span className="text-white">
                                                    <span className="mr-2 font-mono text-atalaya-text-dim">
                                                        {i + 1}.
                                                    </span>
                                                    {p.codigo} — {p.nombre}
                                                    <span className="ml-2 text-xs text-atalaya-text-dim">
                                                        {p.tipo_punto}
                                                    </span>
                                                </span>
                                                <span className="whitespace-nowrap font-mono text-atalaya-text-muted">
                                                    {p.cantidad} ·{' '}
                                                    {porcentaje(p.cantidad, conUbicacion)}
                                                </span>
                                            </div>
                                            <div className="mt-1 h-1.5 bg-atalaya-canvas">
                                                <div
                                                    className="h-full rounded-r bg-atalaya-cyan"
                                                    style={{
                                                        width: `${(p.cantidad / maxPunto) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Panel>

                        <Panel titulo="Por tipo de novedad">
                            {e.por_tipo.length === 0 ? (
                                <p className="text-sm text-atalaya-text-dim">
                                    Sin novedades en el período.
                                </p>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="text-left font-mono text-[10px] font-bold uppercase tracking-wider text-atalaya-text-muted">
                                            <th className="py-2 pr-4">Tipo</th>
                                            <th className="py-2 pr-4">Categoría</th>
                                            <th className="py-2 text-right">Novedades</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-atalaya-border">
                                        {e.por_tipo.map((t) => (
                                            <tr key={t.nombre}>
                                                <td className="py-1.5 pr-4 text-white">
                                                    {t.nombre}
                                                </td>
                                                <td className="py-1.5 pr-4 text-atalaya-text-muted">
                                                    {t.categoria}
                                                </td>
                                                <td className="py-1.5 text-right font-mono text-atalaya-text-muted">
                                                    {t.cantidad} ·{' '}
                                                    {porcentaje(t.cantidad, e.total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
