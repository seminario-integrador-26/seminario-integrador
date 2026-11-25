import { colorGrupo } from '@/catalogos';
import { Delta, PestanasTableros, Tarjeta } from '@/Components/Tableros/Encabezado';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

type EstadoNotificacion = 'enviada' | 'pendiente' | 'fallida';

/** TableroOperativoService::calcular. */
interface Tablero {
    ultima_hora: {
        cantidad: number;
        promedio: number;
        variacion: number | null;
        /** ≥ 50 % sobre el promedio de la misma hora. */
        pico: boolean;
        desde: string;
        hasta: string;
        dias: number;
    };
    turno: {
        abierto: boolean;
        franja: string;
        dia: string;
        grupo_guardia: number | null;
        desde: string;
        cantidad: number;
        promedio: number;
        promedio_a_esta_altura: number;
        porcentaje: number | null;
        semanas: number;
    };
    distribucion: { clave: string; nombre: string; cantidad: number; porcentaje: number }[];
    notificaciones: {
        enviadas: number;
        fallidas: number;
        pendientes: number;
        efectividad: number | null;
        canal: string;
        ultimas_fallidas: { id: number; evento_id: number; codigo: string | null; grupo: string | null; error: string | null; hora: string }[];
    };
    feed: {
        id: number;
        codigo: string;
        fecha: string;
        hora: string;
        tipo: string;
        categoria: string;
        categoria_nombre: string;
        punto: string | null;
        punto_nombre: string | null;
        notificacion: EstadoNotificacion | null;
    }[];
    /** hh:mm:ss */
    actualizado: string;
}

interface Props {
    tablero: Tablero;
    /** Segundos entre actualizaciones (parámetro del sistema). */
    intervaloRefresco: number;
}

const CANALES: Record<string, string> = { telegram: 'Telegram', whatsapp: 'WhatsApp', log: 'Registro local (desarrollo)' };

const ESTADO_NOTIFICACION: Record<EstadoNotificacion, { icono: string; texto: string; clase: string }> = {
    enviada: { icono: '✓', texto: 'Enviada', clase: 'text-emerald-400' },
    pendiente: { icono: '…', texto: 'En cola', clase: 'text-atalaya-amber' },
    fallida: { icono: '✕', texto: 'Fallida', clase: 'text-atalaya-crimson' },
};

/** Velocímetro semicircular: valor contra el promedio (marca blanca). */
function Velocimetro({ valor, promedio }: { valor: number; promedio: number }) {
    const maximo = Math.max(promedio * 1.5, valor, 1);
    const r = 80;
    const cx = 100;
    const cy = 95;
    const largo = Math.PI * r;
    const fraccion = Math.min(valor / maximo, 1);
    // Ángulo de la marca del promedio, de 180° (izquierda) a 0° (derecha).
    const angulo = Math.PI * (1 - Math.min(promedio / maximo, 1));
    const sobre = promedio > 0 && valor > promedio;
    const arco = `M ${cx - r} ${cy} A ${r} ${r} 0 0 1 ${cx + r} ${cy}`;

    return (
        <svg viewBox="0 0 200 110" className="mx-auto w-full max-w-[240px]" role="img" aria-label={`${valor} novedades; promedio ${promedio}`}>
            <path d={arco} fill="none" stroke="#1E3A5F" strokeWidth={14} />
            <path
                d={arco}
                fill="none"
                stroke={sobre ? '#EF4444' : '#00D2FF'}
                strokeWidth={14}
                strokeDasharray={`${largo * fraccion} ${largo}`}
            />
            {promedio > 0 && (
                <line
                    x1={cx + (r - 12) * Math.cos(angulo)}
                    y1={cy - (r - 12) * Math.sin(angulo)}
                    x2={cx + (r + 12) * Math.cos(angulo)}
                    y2={cy - (r + 12) * Math.sin(angulo)}
                    stroke="#FFFFFF"
                    strokeWidth={3}
                />
            )}
            <text x={cx} y={cy - 12} textAnchor="middle" fill="#FFFFFF" fontSize={30} fontWeight={700} fontFamily="monospace">
                {valor}
            </text>
            <text x={cx} y={cy + 6} textAnchor="middle" fill="#94A3B8" fontSize={10}>
                de {promedio} promedio
            </text>
        </svg>
    );
}

export default function Operativo({ tablero: t, intervaloRefresco }: Props) {
    const [actualizando, setActualizando] = useState(false);

    // Tiempo real: solo se pide la prop `tablero` (sin recargar la página).
    usePoll(intervaloRefresco * 1000, {
        only: ['tablero'],
        onStart: () => setActualizando(true),
        onFinish: () => setActualizando(false),
    });

    const totalDistribucion = t.distribucion.reduce((n, d) => n + d.cantidad, 0);
    const n = t.notificaciones;
    const resueltas = n.enviadas + n.fallidas;

    return (
        <AuthenticatedLayout
            header={<h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">Tableros</h2>}
        >
            <Head title="Control operativo" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <PestanasTableros
                        extra={
                            <div className="flex items-center gap-3 font-mono text-xs text-atalaya-text-dim" aria-live="polite">
                                <span className={`inline-block h-2 w-2 rounded-full ${actualizando ? 'animate-pulse bg-atalaya-cyan' : 'bg-emerald-400'}`} />
                                {actualizando ? 'Actualizando…' : `En vivo · actualizado ${t.actualizado} · cada ${intervaloRefresco} s`}
                                <button
                                    type="button"
                                    onClick={() => router.reload({ only: ['tablero'] })}
                                    className="border border-atalaya-border px-2.5 py-1 font-bold uppercase text-atalaya-cyan hover:bg-atalaya-cyan/10"
                                >
                                    Actualizar ahora
                                </button>
                            </div>
                        }
                    />

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {/* KPI 1: velocidad de novedades (última hora). */}
                        <Tarjeta
                            titulo="Última hora"
                            nota={`${t.ultima_hora.desde} a ${t.ultima_hora.hasta} hs`}
                            className={t.ultima_hora.pico ? '!border-atalaya-crimson bg-atalaya-crimson/10' : ''}
                        >
                            <p className={`font-mono text-5xl font-bold ${t.ultima_hora.pico ? 'text-atalaya-crimson' : 'text-white'}`}>
                                {t.ultima_hora.cantidad}
                            </p>
                            <p className="mt-2 text-xs text-atalaya-text-muted">
                                Promedio de esta hora en los últimos {t.ultima_hora.dias} días: <span className="font-mono text-white">{t.ultima_hora.promedio}</span>
                            </p>
                            <p className="mt-1 text-xs">
                                <Delta valor={t.ultima_hora.variacion} sinDatos="sin historial de esta hora" />
                            </p>
                            {t.ultima_hora.pico && (
                                <p role="alert" className="mt-3 border border-atalaya-crimson px-2 py-1 text-center font-mono text-[11px] font-bold uppercase tracking-wider text-atalaya-crimson">
                                    ⚠ Pico de actividad
                                </p>
                            )}
                        </Tarjeta>

                        {/* KPI 2: estado del turno actual. */}
                        <Tarjeta
                            titulo={t.turno.abierto ? 'Turno en curso' : 'Sin turno abierto'}
                            nota={`${t.turno.franja} de los ${t.turno.dia.toLowerCase()}${t.turno.grupo_guardia ? ` · Grupo ${t.turno.grupo_guardia}` : ''} · desde ${t.turno.desde}`}
                        >
                            <Velocimetro valor={t.turno.cantidad} promedio={t.turno.promedio} />
                            <p className="mt-1 text-center text-xs text-atalaya-text-muted">
                                Promedio de las últimas {t.turno.semanas} semanas
                                <br />A esta altura del turno: <span className="font-mono text-white">{t.turno.promedio_a_esta_altura}</span>
                            </p>
                        </Tarjeta>

                        {/* KPI 3: distribución por grupo de evento, últimas 24 h. */}
                        <Tarjeta titulo="Últimas 24 horas" nota={`${totalDistribucion} novedades por grupo`}>
                            {totalDistribucion === 0 ? (
                                <p className="py-10 text-center text-sm text-atalaya-text-dim">Sin novedades en las últimas 24 horas.</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={130}>
                                    <PieChart>
                                        <Pie
                                            data={t.distribucion}
                                            dataKey="cantidad"
                                            nameKey="nombre"
                                            innerRadius={38}
                                            outerRadius={60}
                                            paddingAngle={2}
                                            stroke="#0B1C30"
                                            isAnimationActive={false}
                                        >
                                            {t.distribucion.map((d) => (
                                                <Cell key={d.clave} fill={colorGrupo(d.clave)} />
                                            ))}
                                        </Pie>
                                        <Tooltip
                                            contentStyle={{ background: '#031427', border: '1px solid #1E3A5F', fontSize: 12 }}
                                            itemStyle={{ color: '#E2E8F0' }}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                            <ul className="mt-2 space-y-1 text-xs">
                                {t.distribucion.map((d) => (
                                    <li key={d.clave} className="flex items-center justify-between gap-2">
                                        <span className="flex items-center gap-2 text-atalaya-text-muted">
                                            <span className="inline-block h-2.5 w-2.5" style={{ backgroundColor: colorGrupo(d.clave) }} />
                                            {d.nombre}
                                        </span>
                                        <span className="font-mono text-white">
                                            {d.porcentaje}% <span className="text-atalaya-text-dim">({d.cantidad})</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Tarjeta>

                        {/* KPI 4: salud de las notificaciones del día. */}
                        <Tarjeta
                            titulo="Notificaciones de hoy"
                            nota={`Canal: ${CANALES[n.canal] ?? n.canal}`}
                            className={n.fallidas > 0 ? '!border-atalaya-crimson' : ''}
                        >
                            <p className="font-mono text-5xl font-bold text-white">
                                {n.efectividad === null ? '—' : `${n.efectividad}%`}
                            </p>
                            <p className="mt-1 text-xs text-atalaya-text-muted">enviadas con éxito</p>
                            <div className="mt-3 flex h-2 overflow-hidden bg-atalaya-canvas" aria-hidden>
                                {resueltas > 0 && (
                                    <>
                                        <div className="bg-emerald-500" style={{ width: `${(n.enviadas / resueltas) * 100}%` }} />
                                        <div className="bg-atalaya-crimson" style={{ width: `${(n.fallidas / resueltas) * 100}%` }} />
                                    </>
                                )}
                            </div>
                            <dl className="mt-2 grid grid-cols-3 gap-2 text-center font-mono text-xs">
                                <div>
                                    <dt className="text-atalaya-text-dim">Enviadas</dt>
                                    <dd className="text-emerald-400">{n.enviadas}</dd>
                                </div>
                                <div>
                                    <dt className="text-atalaya-text-dim">Fallidas</dt>
                                    <dd className="text-atalaya-crimson">{n.fallidas}</dd>
                                </div>
                                <div>
                                    <dt className="text-atalaya-text-dim">En cola</dt>
                                    <dd className="text-atalaya-amber">{n.pendientes}</dd>
                                </div>
                            </dl>
                            {n.fallidas > 0 && (
                                <p role="alert" className="mt-3 text-xs text-red-200">
                                    Hay notificaciones que no llegaron: avisar a mano o reenviar desde la novedad (US-007).
                                </p>
                            )}
                        </Tarjeta>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        {/* Feed de actividad reciente. */}
                        <Tarjeta titulo="Actividad reciente" nota="Últimas 10 novedades ingresadas" className="lg:col-span-2">
                            {t.feed.length === 0 ? (
                                <p className="text-sm text-atalaya-text-dim">Todavía no hay novedades registradas.</p>
                            ) : (
                                <div className="max-h-[420px] overflow-y-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="sticky top-0 bg-atalaya-surface font-mono text-[10px] uppercase tracking-widest text-atalaya-text-dim">
                                            <tr>
                                                <th className="py-2 pr-3 font-bold">Hora</th>
                                                <th className="py-2 pr-3 font-bold">Tipo</th>
                                                <th className="py-2 pr-3 font-bold">PM</th>
                                                <th className="py-2 text-right font-bold">Notificación</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-atalaya-border">
                                            {t.feed.map((e) => {
                                                const estado = e.notificacion ? ESTADO_NOTIFICACION[e.notificacion] : null;

                                                return (
                                                    <tr
                                                        key={e.id}
                                                        onClick={() => router.visit(route('eventos.show', e.id))}
                                                        className="cursor-pointer hover:bg-atalaya-elevated/60"
                                                    >
                                                        <td className="whitespace-nowrap py-2 pr-3 font-mono text-white">
                                                            {e.hora}
                                                            <span className="ml-1 text-[10px] text-atalaya-text-dim">{e.fecha}</span>
                                                        </td>
                                                        <td className="py-2 pr-3">
                                                            <span className="mr-2 inline-block h-2 w-2" style={{ backgroundColor: colorGrupo(e.categoria) }} title={e.categoria_nombre} />
                                                            <span className="text-white">{e.tipo}</span>
                                                            <span className="ml-2 text-[11px] text-atalaya-text-dim">{e.categoria_nombre}</span>
                                                        </td>
                                                        <td className="py-2 pr-3 font-mono text-atalaya-text-muted" title={e.punto_nombre ?? undefined}>
                                                            {e.punto ?? '—'}
                                                        </td>
                                                        <td className="py-2 text-right">
                                                            {estado ? (
                                                                <span className={`font-mono text-xs font-bold ${estado.clase}`}>
                                                                    <span aria-hidden>{estado.icono}</span> {estado.texto}
                                                                </span>
                                                            ) : (
                                                                <span className="text-xs text-atalaya-text-dim">No se notifica</span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Tarjeta>

                        <Tarjeta titulo="Notificaciones fallidas de hoy" nota="Clic para abrir la novedad y reenviar">
                            {n.ultimas_fallidas.length === 0 ? (
                                <p className="text-sm text-emerald-400">Sin fallas en el día.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {n.ultimas_fallidas.map((f) => (
                                        <li key={f.id}>
                                            <Link
                                                href={route('eventos.show', f.evento_id)}
                                                className="block border border-atalaya-crimson/50 bg-atalaya-crimson/10 px-3 py-2 text-xs hover:border-atalaya-crimson"
                                            >
                                                <span className="font-mono font-bold text-white">{f.codigo}</span>
                                                <span className="ml-2 text-atalaya-text-muted">
                                                    {f.hora} · {f.grupo ?? 'Grupo eliminado'}
                                                </span>
                                                {f.error && <span className="mt-1 block truncate text-red-200">{f.error}</span>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Tarjeta>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
