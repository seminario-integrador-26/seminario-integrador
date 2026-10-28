import EstadoNotificaciones from '@/Components/EstadoNotificaciones';
import FlashMessages from '@/Components/FlashMessages';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Notificacion, PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Fe de errata ya legible (EventoController::show). */
interface ErrataDetalle {
    id: number;
    /** Etiqueta del campo corregido (Errata::CAMPOS); null en las de solo motivo. */
    campo: string | null;
    anterior: string | null;
    nuevo: string | null;
    motivo: string;
    autor: string | null;
    /** dd/mm/aaaa hh:mm:ss */
    fecha: string | null;
}

/** Detalle del evento con sus valores vigentes (EventoController::show). */
interface EventoDetalle {
    id: number;
    tipo: string;
    /** Nombre del GrupoEvento. */
    categoria: string;
    informativo: boolean;
    cuantificable: boolean;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm:ss */
    hora: string;
    descripcion: string | null;
    punto: {
        codigo: string;
        nombre: string;
        tipo: string;
        barrio: string | null;
        cuadrante: string | null;
        latitud: number | null;
        longitud: number | null;
    } | null;
    /** Sin PM: barrio y dirección cargados a mano. */
    barrio: string | null;
    direccion: string | null;
    erratas: ErrataDetalle[];
    autor: string | null;
    registrado: string | null;
    notificaciones: Notificacion[];
}

/** Badge por grupo de evento, alineado al semáforo del mapa. */
const categoriaBadge: Record<string, string> = {
    Policía: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    'Guardia Local':
        'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    Internos: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

const badge =
    'inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4';

function Dato({ etiqueta, children, className = '' }: { etiqueta: string; children?: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <dt className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                {etiqueta}
            </dt>
            <dd className="mt-1 text-sm text-white">{children ?? '—'}</dd>
        </div>
    );
}

/**
 * US-010: detalle completo de un evento. Solo lectura: los eventos son
 * inmutables (las correcciones van por fe de errata).
 */
export default function Show({ evento, puedeCorregir }: { evento: EventoDetalle; puedeCorregir: boolean }) {
    const { auth } = usePage<PageProps>().props;
    const puedeReenviar = auth.roles.includes('Supervisor');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                        Novedad #{evento.id}
                    </h2>
                    <div className="flex items-center gap-4">
                        {puedeCorregir && (
                            <Link
                                href={route('erratas.create', evento.id)}
                                className="border border-atalaya-amber px-3 py-1 font-mono text-xs font-bold uppercase text-atalaya-amber hover:bg-atalaya-amber/10"
                            >
                                Fe de errata
                            </Link>
                        )}
                        <Link
                            href={route('eventos.index')}
                            className="font-mono text-xs font-bold uppercase text-atalaya-cyan hover:underline"
                        >
                            ← Volver a novedades
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Novedad #${evento.id}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <FlashMessages />

                    {evento.erratas.length > 0 && (
                        <p className="border border-atalaya-amber/50 bg-atalaya-amber/10 px-4 py-2 text-sm text-amber-100">
                            Esta novedad tiene {evento.erratas.length} fe(s) de
                            errata; el detalle está al final.
                        </p>
                    )}

                    <section className="border border-atalaya-border bg-atalaya-surface p-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="mr-2 text-lg font-semibold text-white">
                                {evento.tipo}
                            </h3>
                            <span
                                className={
                                    badge +
                                    ' ' +
                                    (categoriaBadge[evento.categoria] ??
                                        'border-atalaya-border text-atalaya-text-muted')
                                }
                            >
                                {evento.categoria}
                            </span>
                            {!evento.punto && !evento.direccion && !evento.barrio && (
                                <span
                                    className={`${badge} border-atalaya-cyan/60 text-atalaya-cyan`}
                                >
                                    Sin ubicación
                                </span>
                            )}
                            {!evento.cuantificable && (
                                <span
                                    className={`${badge} border-atalaya-border text-atalaya-text-muted`}
                                    title="No cuenta en las estadísticas"
                                >
                                    No cuantificable
                                </span>
                            )}
                        </div>

                        <dl className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                            <Dato etiqueta="Fecha de la novedad">{evento.fecha}</Dato>
                            <Dato etiqueta="Horario de la novedad">
                                <span className="font-mono">{evento.hora}</span>
                            </Dato>
                            <Dato
                                etiqueta="Descripción"
                                className="sm:col-span-3"
                            >
                                <p className="whitespace-pre-line text-atalaya-text-muted">
                                    {evento.descripcion || '—'}
                                </p>
                            </Dato>
                        </dl>
                    </section>

                    <section className="border border-atalaya-border bg-atalaya-surface p-6">
                        <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                            Ubicación
                        </h3>
                        {evento.punto ? (
                            <dl className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                                <Dato etiqueta="Punto de monitoreo">
                                    {evento.punto.codigo} — {evento.punto.nombre}
                                </Dato>
                                <Dato etiqueta="Tipo de PM">
                                    {evento.punto.tipo}
                                </Dato>
                                {evento.direccion && (
                                    <Dato etiqueta="Dirección">
                                        {evento.direccion}
                                    </Dato>
                                )}
                                <Dato etiqueta="Barrio / cuadrante">
                                    {[evento.punto.barrio, evento.punto.cuadrante].filter(Boolean).join(' · ') || '—'}
                                </Dato>
                                <Dato etiqueta="Coordenadas">
                                    <span className="font-mono text-xs">
                                        {evento.punto.latitud},{' '}
                                        {evento.punto.longitud}
                                    </span>
                                </Dato>
                            </dl>
                        ) : evento.direccion || evento.barrio ? (
                            <>
                                <dl className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                                    <Dato etiqueta="Dirección">
                                        {evento.direccion || '—'}
                                    </Dato>
                                    <Dato etiqueta="Barrio">
                                        {evento.barrio || '—'}
                                    </Dato>
                                </dl>
                                <p className="mt-4 text-xs text-atalaya-text-dim">
                                    Sin punto de monitoreo: no figura en el mapa
                                    ni en las estadísticas por PM.
                                </p>
                            </>
                        ) : (
                            <p className="mt-4 text-sm text-atalaya-text-muted">
                                Novedad sin ubicación: no se incluye en las
                                estadísticas por ubicación.
                            </p>
                        )}
                    </section>

                    <section className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="border border-atalaya-border bg-atalaya-surface p-6">
                            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Registro
                            </h3>
                            <dl className="mt-4 space-y-4">
                                <Dato etiqueta="Registró">{evento.autor}</Dato>
                                <Dato etiqueta="Fecha de carga en el sistema">
                                    {evento.registrado}
                                </Dato>
                            </dl>
                        </div>

                        <div className="border border-atalaya-border bg-atalaya-surface p-6">
                            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Notificaciones
                            </h3>
                            <div className="mt-4">
                                <EstadoNotificaciones
                                    notificaciones={evento.notificaciones}
                                    puedeReenviar={puedeReenviar}
                                />
                            </div>
                        </div>
                    </section>

                    {evento.erratas.length > 0 && (
                        <section className="border border-atalaya-border bg-atalaya-surface p-6">
                            <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Fe de erratas
                            </h3>
                            <ol className="mt-4 space-y-4">
                                {evento.erratas.map((e) => (
                                    <li
                                        key={e.id}
                                        className="border-l-2 border-atalaya-amber pl-4 text-sm"
                                    >
                                        {e.campo !== null ? (
                                            <>
                                                <p className="font-medium text-white">
                                                    {e.campo}
                                                </p>
                                                <p className="mt-1 text-atalaya-text-muted">
                                                    <span className="text-atalaya-text-dim line-through">
                                                        {e.anterior ?? '(vacío)'}
                                                    </span>{' '}
                                                    →{' '}
                                                    <span className="text-white">
                                                        {e.nuevo ?? '(vacío)'}
                                                    </span>
                                                </p>
                                                <p className="mt-1 text-xs text-atalaya-text-dim">
                                                    Motivo: {e.motivo}
                                                </p>
                                            </>
                                        ) : (
                                            <p className="whitespace-pre-line text-white">
                                                {e.motivo}
                                            </p>
                                        )}
                                        <p className="text-xs text-atalaya-text-dim">
                                            {e.autor} · {e.fecha}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
