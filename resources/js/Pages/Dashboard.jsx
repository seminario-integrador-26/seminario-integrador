import AbrirTurnoModal from '@/Components/AbrirTurnoModal';
import TacticalMap, { colorDeCategoria } from '@/Components/TacticalMap';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

// US-025: recuerda por sesión que el supervisor pospuso abrir turno.
const CLAVE_POSPONER_TURNO = 'atalaya:turno-pospuesto';

const TODAS = 'TODAS';

/** Clases del badge de categoría, alineadas al semáforo del mapa. */
const badgeDeCategoria = (categoria) => {
    switch (categoria) {
        case 'Seguridad Pública':
            return 'text-atalaya-crimson border-atalaya-crimson bg-atalaya-crimson/10';
        case 'Convivencia Urbana':
            return 'text-atalaya-amber border-atalaya-amber bg-atalaya-amber/10';
        case 'Prevención':
            return 'text-atalaya-orange border-atalaya-orange bg-atalaya-orange/10';
        default:
            return 'text-atalaya-cyan border-atalaya-cyan bg-atalaya-cyan/10';
    }
};

export default function Dashboard({
    turno,
    eventos,
    conteoPorCategoria,
    puntosMonitoreo,
    resumenPuntos,
}) {
    const { auth, turnoSupervisor } = usePage().props;
    const esSupervisor = (auth.roles ?? []).includes('Supervisor');

    const [filtro, setFiltro] = useState(TODAS);
    const [seleccionado, setSeleccionado] = useState(null);

    // Modal de apertura de turno (US-025): sólo Supervisor sin turno activo y que
    // no lo haya pospuesto en esta sesión.
    const [pospuesto, setPospuesto] = useState(
        () => sessionStorage.getItem(CLAVE_POSPONER_TURNO) === '1',
    );
    const mostrarModalTurno = esSupervisor && !turnoSupervisor && !pospuesto;

    const posponerTurno = () => {
        sessionStorage.setItem(CLAVE_POSPONER_TURNO, '1');
        setPospuesto(true);
    };

    const eventosFiltrados = useMemo(
        () =>
            filtro === TODAS
                ? eventos
                : eventos.filter((ev) => ev.categoria === filtro),
        [eventos, filtro],
    );

    // Sólo los eventos con punto de monitoreo se pueden dibujar (RN-03).
    const eventosGeorreferenciados = useMemo(
        () =>
            eventosFiltrados.filter(
                (ev) => ev.latitud !== null && ev.longitud !== null,
            ),
        [eventosFiltrados],
    );

    const categorias = Object.keys(conteoPorCategoria);
    const cuantificables = Object.values(conteoPorCategoria).reduce(
        (total, n) => total + n,
        0,
    );

    return (
        <AuthenticatedLayout>
            <Head title="Panel" />

            <AbrirTurnoModal
                show={mostrarModalTurno}
                onPosponer={posponerTurno}
            />

            <div className="flex min-h-[calc(100vh-4rem)] w-full flex-col bg-atalaya-canvas font-sans">
                {/* 1. Barra de telemetría */}
                <section className="border-b border-atalaya-border bg-atalaya-surface">
                    <div className="swiss-grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Eventos del turno */}
                        <div className="swiss-panel flex flex-col justify-between p-4">
                            <div className="flex items-center justify-between font-mono text-xs uppercase text-atalaya-text-muted">
                                <span>
                                    [{' '}
                                    {turno
                                        ? 'Eventos en turno'
                                        : 'Últimos eventos'}{' '}
                                    ]
                                </span>
                                <span className="text-[10px] text-atalaya-cyan">
                                    En vivo
                                </span>
                            </div>
                            <div className="mt-2 flex flex-wrap items-baseline gap-3">
                                <span className="font-mono text-3xl font-black tracking-tight text-white">
                                    {eventos.length}
                                </span>
                                <div className="flex flex-wrap items-center gap-2 font-mono text-[10px]">
                                    <span className="font-bold text-atalaya-crimson">
                                        ● {conteoPorCategoria['Seguridad Pública'] ?? 0}{' '}
                                        SEG
                                    </span>
                                    <span className="font-bold text-atalaya-amber">
                                        ● {conteoPorCategoria['Convivencia Urbana'] ?? 0}{' '}
                                        CONV
                                    </span>
                                    <span className="font-bold text-atalaya-orange">
                                        ● {conteoPorCategoria['Prevención'] ?? 0} PREV
                                    </span>
                                </div>
                            </div>
                            <div className="mt-1 font-mono text-[10px] text-atalaya-text-dim">
                                {cuantificables} cuantificables ·{' '}
                                {eventos.length - cuantificables} informativos
                            </div>
                        </div>

                        {/* Red de monitoreo */}
                        <div className="swiss-panel flex flex-col justify-between p-4">
                            <div className="flex items-center justify-between font-mono text-xs uppercase text-atalaya-text-muted">
                                <span>[ Puntos de monitoreo ]</span>
                            </div>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className="font-mono text-3xl font-black tracking-tight text-white">
                                    {resumenPuntos.total}
                                </span>
                                <span className="font-mono text-xs text-atalaya-cyan">
                                    {Object.entries(
                                        resumenPuntos.por_jurisdiccion,
                                    )
                                        .map(([j, n]) => `${n} ${j}`)
                                        .join(' · ')}
                                </span>
                            </div>
                            {/* TODO: el modelo no releva estado operativo de cámara (en línea / mantenimiento). */}
                        </div>

                        {/* Turno vigente */}
                        <div className="swiss-panel flex flex-col justify-between p-4">
                            <div className="flex items-center justify-between font-mono text-xs uppercase text-atalaya-text-muted">
                                <span>[ Guardia vigente ]</span>
                                {turno ? (
                                    <span className="text-[10px] font-bold text-atalaya-orange">
                                        Turno #{turno.id}
                                    </span>
                                ) : (
                                    <span className="text-[10px] font-bold text-atalaya-crimson">
                                        Sin turno
                                    </span>
                                )}
                            </div>
                            {turno ? (
                                <div className="mt-2 flex flex-col">
                                    <span className="font-mono text-sm font-bold uppercase tracking-wider text-white">
                                        Desde {turno.hora_inicio} ·{' '}
                                        {turno.fecha}
                                    </span>
                                    <span className="font-mono text-[11px] text-atalaya-text-muted">
                                        {turno.supervisor ?? 'Sin supervisor'} ·{' '}
                                        {turno.transcurrido} transcurridos
                                    </span>
                                </div>
                            ) : (
                                <div className="mt-2 font-mono text-[11px] leading-relaxed text-atalaya-text-muted">
                                    No hay turno de guardia abierto. No se pueden
                                    registrar eventos hasta abrir uno (RN-05).
                                </div>
                            )}
                        </div>

                        {/* Acciones */}
                        <div className="swiss-panel flex items-center justify-between gap-2 p-4">
                            <div className="flex w-full flex-col gap-2 font-mono text-xs">
                                <Link
                                    href={route('eventos.index')}
                                    className="w-full border border-atalaya-border bg-atalaya-elevated px-3 py-1.5 text-center font-bold uppercase tracking-wider text-atalaya-cyan transition hover:bg-atalaya-border"
                                >
                                    [ Consultar eventos ]
                                </Link>
                                <div className="grid grid-cols-2 gap-2">
                                    {/* TODO: exportadores PDF/A y .xlsx pendientes. */}
                                    <button
                                        type="button"
                                        disabled
                                        title="Pendiente: reporte de turno en PDF/A"
                                        className="cursor-not-allowed border border-atalaya-border bg-atalaya-canvas px-2 py-1 text-center text-[10px] uppercase text-atalaya-text-dim opacity-60"
                                    >
                                        PDF/A turno
                                    </button>
                                    <button
                                        type="button"
                                        disabled
                                        title="Pendiente: exportación .xlsx de datos abiertos"
                                        className="cursor-not-allowed border border-atalaya-border bg-atalaya-canvas px-2 py-1 text-center text-[10px] uppercase text-atalaya-text-dim opacity-60"
                                    >
                                        Datos .xlsx
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 2. Cartografía + feed */}
                <section className="swiss-grid min-h-[680px] flex-1 grid-cols-1 lg:grid-cols-12">
                    {/* Visor cartográfico */}
                    <div className="swiss-panel relative flex flex-col lg:col-span-7 xl:col-span-8">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-atalaya-border bg-atalaya-surface px-4 py-2.5">
                            <div className="flex items-center gap-3">
                                <span className="font-mono text-xs font-bold uppercase tracking-widest text-white">
                                    + Situación geoespacial
                                </span>
                                <span className="font-mono text-[10px] text-atalaya-text-dim">
                                    [ Villa María ]
                                </span>
                            </div>

                            <div className="flex flex-wrap items-center gap-1 font-mono text-[10px]">
                                <button
                                    onClick={() => setFiltro(TODAS)}
                                    className={`border px-2 py-1 uppercase transition ${
                                        filtro === TODAS
                                            ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-white'
                                            : 'border-atalaya-border text-atalaya-text-muted hover:text-white'
                                    }`}
                                >
                                    Todos ({eventos.length})
                                </button>
                                {categorias.map((categoria) => {
                                    const activo = filtro === categoria;
                                    const color = colorDeCategoria(categoria);

                                    return (
                                        <button
                                            key={categoria}
                                            onClick={() => setFiltro(categoria)}
                                            style={
                                                activo
                                                    ? {
                                                          borderColor: color,
                                                          backgroundColor: `${color}33`,
                                                      }
                                                    : undefined
                                            }
                                            className={`border px-2 py-1 uppercase transition ${
                                                activo
                                                    ? 'text-white'
                                                    : 'border-atalaya-border text-atalaya-text-muted hover:text-white'
                                            }`}
                                        >
                                            ● {categoria} (
                                            {conteoPorCategoria[categoria] ?? 0})
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="relative min-h-[480px] w-full flex-1">
                            <TacticalMap
                                eventos={eventosGeorreferenciados}
                                puntosMonitoreo={puntosMonitoreo}
                                eventoSeleccionado={seleccionado}
                                onSeleccionarEvento={setSeleccionado}
                            />

                            <div className="pointer-events-none absolute bottom-4 left-4 z-[1000] space-y-1 border border-atalaya-border bg-atalaya-surface/90 p-3 font-mono text-[10px] backdrop-blur-sm">
                                <div className="mb-1 font-bold tracking-wider text-white">
                                    [ Leyenda ]
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-2.5 w-2.5 border border-atalaya-crimson bg-atalaya-crimson/50"></span>
                                    <span className="text-atalaya-text-muted">
                                        Seguridad Pública
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-2.5 w-2.5 border border-atalaya-amber bg-atalaya-amber/50"></span>
                                    <span className="text-atalaya-text-muted">
                                        Convivencia Urbana
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-2.5 w-2.5 border border-atalaya-orange bg-atalaya-orange/50"></span>
                                    <span className="text-atalaya-text-muted">
                                        Prevención
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-2 w-2 border border-atalaya-cyan bg-atalaya-cyan"></span>
                                    <span className="text-atalaya-text-muted">
                                        Punto de monitoreo
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Feed de eventos */}
                    <div className="swiss-panel flex flex-col border-t border-atalaya-border lg:col-span-5 lg:border-t-0 xl:col-span-4">
                        <div className="flex items-center justify-between border-b border-atalaya-border bg-atalaya-surface px-4 py-2.5">
                            <span className="font-mono text-xs font-bold uppercase tracking-widest text-white">
                                + Feed de eventos registrados
                            </span>
                            <span className="font-mono text-[10px] text-atalaya-cyan">
                                Inmutables (RN-01)
                            </span>
                        </div>

                        <div className="max-h-[580px] flex-1 divide-y divide-atalaya-border overflow-y-auto">
                            {eventosFiltrados.length === 0 && (
                                <div className="p-6 text-center font-mono text-xs text-atalaya-text-muted">
                                    Sin eventos registrados
                                    {filtro !== TODAS && ' para este filtro'}.
                                </div>
                            )}

                            {eventosFiltrados.map((ev) => {
                                const activo = seleccionado?.id === ev.id;

                                return (
                                    <div
                                        key={ev.id}
                                        onClick={() => setSeleccionado(ev)}
                                        className={`cursor-pointer p-3.5 font-mono transition ${
                                            activo
                                                ? 'border-l-2 border-l-atalaya-cyan bg-atalaya-elevated/70'
                                                : 'hover:bg-atalaya-canvas/50'
                                        }`}
                                    >
                                        <div className="mb-1.5 flex items-center justify-between gap-2">
                                            <span className="text-xs font-bold tracking-wider text-white">
                                                {ev.codigo}
                                            </span>
                                            <span
                                                className={`border px-1.5 py-0.5 text-[9px] font-bold uppercase ${badgeDeCategoria(
                                                    ev.categoria,
                                                )}`}
                                            >
                                                {ev.categoria}
                                            </span>
                                        </div>

                                        <div className="mb-1 font-sans text-xs font-medium text-white">
                                            {ev.tipo}
                                        </div>

                                        {ev.descripcion && (
                                            <div className="line-clamp-2 font-sans text-[11px] text-atalaya-text-muted">
                                                {ev.descripcion}
                                            </div>
                                        )}

                                        <div className="mt-2 flex items-center justify-between text-[11px] text-atalaya-text-muted">
                                            <span className="max-w-[190px] truncate">
                                                {ev.ubicacion ?? 'Sin ubicación'}
                                            </span>
                                            <span className="text-atalaya-text-dim">
                                                {ev.hora}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Detalle del evento seleccionado */}
                        {seleccionado && (
                            <div className="flex flex-col gap-3 border-t border-atalaya-border bg-atalaya-surface p-4 font-mono text-xs">
                                <div className="flex items-center justify-between border-b border-atalaya-border pb-2">
                                    <span className="font-bold text-white">
                                        Detalle: {seleccionado.codigo}
                                    </span>
                                    {/* TODO: fe de errata cuando exista ErrataController + tabla. */}
                                    <button
                                        type="button"
                                        disabled
                                        title="Pendiente: registro de fe de errata"
                                        className="cursor-not-allowed border border-atalaya-border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-atalaya-text-dim opacity-60"
                                    >
                                        [ + Fe de errata ]
                                    </button>
                                </div>

                                <div className="space-y-1.5 text-[11px]">
                                    <div>
                                        <span className="text-atalaya-text-muted">
                                            TIPO:{' '}
                                        </span>
                                        <span className="text-white">
                                            {seleccionado.tipo}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-atalaya-text-muted">
                                            UBICACIÓN:{' '}
                                        </span>
                                        <span className="text-white">
                                            {seleccionado.ubicacion ??
                                                'No requiere ubicación'}
                                        </span>
                                    </div>
                                    {seleccionado.latitud !== null && (
                                        <div>
                                            <span className="text-atalaya-text-muted">
                                                COORD:{' '}
                                            </span>
                                            <span className="text-atalaya-cyan">
                                                {Number(
                                                    seleccionado.latitud,
                                                ).toFixed(4)}
                                                ,{' '}
                                                {Number(
                                                    seleccionado.longitud,
                                                ).toFixed(4)}
                                            </span>
                                        </div>
                                    )}
                                    {seleccionado.timestamp_video && (
                                        <div>
                                            <span className="text-atalaya-text-muted">
                                                TIMESTAMP VIDEO:{' '}
                                            </span>
                                            <span className="text-white">
                                                {seleccionado.timestamp_video}
                                            </span>
                                        </div>
                                    )}
                                    <div>
                                        <span className="text-atalaya-text-muted">
                                            REGISTRADO:{' '}
                                        </span>
                                        <span className="text-white">
                                            {seleccionado.fecha}{' '}
                                            {seleccionado.hora} ·{' '}
                                            {seleccionado.autor ?? 'N/D'} · turno
                                            #{seleccionado.turno_id}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
