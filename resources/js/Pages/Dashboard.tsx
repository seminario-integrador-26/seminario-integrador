import { colorDeGrupo, colorGrupo, colorTipoPunto, estiloGrupo, TIPOS_PUNTO } from '@/catalogos';
import BuscadorPuntoMapa from '@/Components/BuscadorPuntoMapa';
import TacticalMap, { type Enfoque } from '@/Components/TacticalMap';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { EventoPanel, PageProps, PuntoMapa } from '@/types';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/** Turno abierto (DashboardService::turnoVigente): última Apertura de turno sin Cierre. */
interface TurnoVigente {
    /** Número del evento Apertura de turno. */
    id: number;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm */
    hora_inicio: string;
    supervisor: string | null;
    /** "04h 12m" */
    transcurrido: string;
    /** Etiqueta de la franja ("Mañana"); null en aperturas anteriores al campo. */
    franja: string | null;
    /** Grupo de guardia de turno (1 a 4); null en aperturas anteriores al campo. */
    grupo_guardia: number | null;
}

interface Props {
    turno: TurnoVigente | null;
    eventos: EventoPanel[];
    /** Nombre del GrupoEvento => eventos cuantificables. */
    conteoPorCategoria: Record<string, number>;
    puntosMonitoreo: PuntoMapa[];
    /** por_tipo: código de TipoPuntoMonitoreo => cantidad. */
}

const TODAS = 'TODAS';

/** Minúsculas y sin tildes, para buscar "policia" y encontrar "Policía". */
const normalizar = (texto: string) =>
    texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const dos = (n: string | number) => String(n).padStart(2, '0');

/**
 * Hora de la novedad (hh:mm:ss) dentro del rango [desde, hasta] (hh:mm, con
 * los extremos incluidos al minuto). Si desde > hasta, el rango cruza la
 * medianoche (turno noche, 22:00 a 06:00).
 */
const enRangoHorario = (hora: string, desde: string, hasta: string) => {
    const despues = hora >= `${desde}:00`;
    const antes = hora <= `${hasta}:59`;

    return desde > hasta ? despues || antes : despues && antes;
};

/** ("14", "30") → "14:30"; ("14") → "14:00"; null si no es una hora válida. */
const horaDe = (h: string, m?: string) =>
    Number(h) < 24 && Number(m ?? 0) < 60 ? `${dos(h)}:${dos(m ?? 0)}` : null;

/**
 * Cómo coincide cada palabra del buscador con una novedad:
 *   10/11, 10/11/2026, 10/11/26  → fecha de la novedad (con barra: el
 *                                  guion es para rangos horarios)
 *   14:30, 14.30, 14hs, 9:3       → hora de la novedad (por prefijo)
 *   14-16, 22:00-06:00            → rango horario (cruza la medianoche si hace falta)
 *   cualquier otra cosa           → texto (código, tipo, lugar, descripción, autor)
 */
const coincide = (termino: string, ev: EventoPanel, texto: string): boolean => {
    const fecha = termino.match(/^(\d{1,2})\/(\d{1,2})(?:\/(\d{2}|\d{4}))?$/);
    if (fecha) {
        const [d, m, a] = ev.fecha.split('/');
        const anio = fecha[3] ? (fecha[3].length === 2 ? `20${fecha[3]}` : fecha[3]) : a;
        return dos(fecha[1]) === d && dos(fecha[2]) === m && anio === a;
    }

    const rango = termino.match(/^(\d{1,2})(?:[:.](\d{2}))?(?:hs?)?-(\d{1,2})(?:[:.](\d{2}))?(?:hs?)?$/);
    if (rango) {
        const desde = horaDe(rango[1], rango[2]);
        const hasta = horaDe(rango[3], rango[4]);
        if (desde && hasta) {
            return enRangoHorario(ev.hora, desde, hasta);
        }
    }

    const hora = termino.match(/^(\d{1,2})(?:hs?|[:.](\d{0,2})(?:hs?)?(?::(\d{0,2}))?)$/);
    if (hora) {
        // "9:3" → "09:3", prefijo de "09:35:12".
        const prefijo = [dos(hora[1]), hora[2], hora[3]].filter(Boolean).join(':');
        return ev.hora.startsWith(prefijo);
    }

    return texto.includes(termino);
};

export default function Dashboard({
    turno,
    eventos,
    conteoPorCategoria,
    puntosMonitoreo,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const esSupervisor = (auth.roles ?? []).includes('Supervisor');
    const puedeCorregir = auth.permissions.includes('erratas.crear');
    const puedeAmpliar = auth.permissions.includes('ampliaciones.crear');
    const puedeExportar = auth.permissions.includes('reportes.exportar');

    // "En vivo": cada 30 s recarga solo turno y novedades (no el mapa ni los
    // filtros/búsqueda, que quedan como están). Las cámaras no se recargan.
    usePoll(30_000, { only: ['turno', 'eventos', 'conteoPorCategoria'] });

    const [filtro, setFiltro] = useState(TODAS);
    const [seleccionado, setSeleccionado] = useState<EventoPanel | null>(null);

    const [busqueda, setBusqueda] = useState('');
    // PM elegido en el buscador; `n` fuerza re-enfocar aunque sea el mismo PM.
    const [enfoque, setEnfoque] = useState<Enfoque | null>(null);

    const eventosDeCategoria = useMemo(
        () =>
            filtro === TODAS
                ? eventos
                : eventos.filter((ev) => ev.categoria === filtro),
        [eventos, filtro],
    );

    // Búsqueda en el feed: cada palabra tiene que coincidir (ver `coincide`),
    // sin distinguir mayúsculas ni tildes. Fechas y horas filtran por la fecha
    // y el horario de la novedad: "10/11 14-16 robo".
    const eventosFiltrados = useMemo(() => {
        const terminos = normalizar(busqueda.trim()).split(/\s+/).filter(Boolean);
        if (terminos.length === 0) {
            return eventosDeCategoria;
        }

        return eventosDeCategoria.filter((ev) => {
            const texto = normalizar(
                [ev.codigo, ev.tipo, ev.categoria, ev.ubicacion, ev.descripcion, ev.autor].join(' '),
            );

            return terminos.every((termino) => coincide(termino, ev, texto));
        });
    }, [eventosDeCategoria, busqueda]);

    const cambiarCategoria = (categoria: string) => {
        setFiltro(categoria);
    };

    const enfocarPunto = (punto: PuntoMapa) =>
        setEnfoque((actual) => ({ punto, n: (actual?.n ?? 0) + 1 }));

    const categorias = Object.keys(conteoPorCategoria);

    return (
        <AuthenticatedLayout>
            <Head title="Panel" />

            <div className="flex min-h-[calc(100vh-4rem)] w-full flex-col bg-atalaya-canvas font-sans">
                {/* 1. Barra de telemetría (compacta: el protagonista es el mapa) */}
                <section className="border-b border-atalaya-border bg-atalaya-surface">
                    {/* Turno + novedades; sin Libros de guardia (Supervisor) no va la caja de la derecha. */}
                    <div
                        className={
                            'swiss-grid grid-cols-1 ' +
                            (puedeExportar
                                ? 'sm:grid-cols-[minmax(0,3fr)_minmax(0,4fr)_minmax(0,2fr)]'
                                : 'sm:grid-cols-[minmax(0,3fr)_minmax(0,4fr)]')
                        }
                    >
                        {/* Estado del turno de guardia: abierto (con grupo y franja) o cerrado */}
                        <div
                            className={
                                'swiss-panel flex items-center gap-3 border-l-4 px-4 py-2 font-mono ' +
                                (turno ? 'border-l-emerald-500' : 'border-l-atalaya-crimson')
                            }
                        >
                            <span
                                aria-hidden
                                className={
                                    'h-2.5 w-2.5 shrink-0 rounded-full ' +
                                    (turno ? 'animate-pulse bg-emerald-500' : 'bg-atalaya-crimson')
                                }
                            />
                            {turno ? (
                                <div className="min-w-0">
                                    <div className="truncate text-xs font-black uppercase tracking-wider text-white">
                                        Turno abierto
                                        {turno.grupo_guardia !== null && (
                                            <span className="text-emerald-400">
                                                {' '}· Grupo {turno.grupo_guardia}
                                            </span>
                                        )}
                                    </div>
                                    {(turno.franja || turno.supervisor) && (
                                        <div className="truncate text-[10px] text-atalaya-text-muted">
                                            {[turno.franja, turno.supervisor].filter(Boolean).join(' · ')}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="flex min-w-0 flex-1 items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="truncate text-xs font-black uppercase tracking-wider text-atalaya-crimson">
                                            Turno cerrado
                                        </div>
                                        <div className="truncate text-[10px] text-atalaya-text-muted">
                                            Sin guardia en curso
                                        </div>
                                    </div>
                                    {esSupervisor && (
                                        <Link
                                            href={route('eventos.create')}
                                            className="shrink-0 border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-atalaya-cyan transition hover:bg-atalaya-cyan/25"
                                        >
                                            Abrir turno
                                        </Link>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Eventos del turno */}
                        <div className="swiss-panel flex items-center gap-3 px-4 py-2">
                            <span className="font-mono text-xl font-black leading-none text-white">
                                {eventos.length}
                            </span>
                            <div className="min-w-0 font-mono">
                                <div className="truncate text-[10px] uppercase text-atalaya-text-muted">
                                    {turno ? 'Novedades en turno' : 'Últimas novedades'}{' '}
                                    <span className="text-atalaya-cyan">· En vivo</span>
                                </div>
                                <div className="flex flex-wrap gap-x-2 text-[10px] font-bold">
                                    <span style={{ color: colorGrupo('policia') }}>
                                        ● {conteoPorCategoria['Policía'] ?? 0} POL
                                    </span>
                                    <span style={{ color: colorGrupo('guardia_local') }}>
                                        ● {conteoPorCategoria['Guardia Local'] ?? 0} GL
                                    </span>
                                    <span style={{ color: colorGrupo('internos') }}>
                                        ● {conteoPorCategoria['Internos'] ?? 0} INT
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Libro de guardia en PDF (US-012). Eventos y su Excel se consultan desde el menú. */}
                        {puedeExportar && (
                            <div className="swiss-panel flex items-center px-4 py-2 font-mono text-[10px]">
                                <Link
                                    href={route('reportes.index')}
                                    className="flex-1 border border-atalaya-border bg-atalaya-elevated px-2 py-1 text-center font-bold uppercase tracking-wider text-atalaya-cyan transition hover:bg-atalaya-border"
                                >
                                    Libros de guardia
                                </Link>
                            </div>
                        )}
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
                                    onClick={() => cambiarCategoria(TODAS)}
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
                                    const color = colorDeGrupo(categoria);

                                    return (
                                        <button
                                            key={categoria}
                                            onClick={() =>
                                                cambiarCategoria(categoria)
                                            }
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
                                puntosMonitoreo={puntosMonitoreo}
                                enfoque={enfoque}
                                puedeRegistrar={esSupervisor}
                            />

                            <div className="absolute left-4 top-4 z-[1000] w-72 max-w-[calc(100%-2rem)]">
                                <BuscadorPuntoMapa
                                    puntos={puntosMonitoreo}
                                    onSeleccionar={enfocarPunto}
                                />
                            </div>

                            <div className="pointer-events-none absolute bottom-4 left-4 z-[1000] space-y-1 border border-atalaya-border bg-atalaya-surface/90 p-3 font-mono text-[10px] backdrop-blur-sm">
                                <div className="mb-1 font-bold tracking-wider text-white">
                                    [ Leyenda ]
                                </div>
                                {TIPOS_PUNTO.map(([tipo, nombre]) => (
                                    <div key={tipo} className="flex items-center gap-2">
                                        <span className="inline-block h-2 w-2" style={{ backgroundColor: colorTipoPunto(tipo) }}></span>
                                        <span className="text-atalaya-text-muted">
                                            PM {nombre.toLowerCase()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Libro de guardia */}
                    <div className="swiss-panel flex flex-col border-t border-atalaya-border lg:col-span-5 lg:border-t-0 xl:col-span-4">
                        <div className="space-y-2 border-b border-atalaya-border bg-atalaya-surface px-4 py-2.5">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-mono text-xs font-bold uppercase tracking-widest text-white">
                                    + Libro de guardia
                                </span>
                                {esSupervisor ? (
                                    <Link
                                        href={route('eventos.create')}
                                        className="shrink-0 border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-atalaya-cyan transition hover:bg-atalaya-cyan/25"
                                    >
                                        [ + Registrar ]
                                    </Link>
                                ) : (
                                    <span className="font-mono text-[10px] text-atalaya-cyan">
                                        Inmutables (RN-01)
                                    </span>
                                )}
                            </div>
                            <TextInput
                                type="search"
                                aria-label="Buscar en el libro de guardia"
                                placeholder={`Buscar en ${eventosDeCategoria.length} novedades (tipo, lugar, 10/11, 14:30, 14-16…)`}
                                title="Texto, fecha (10/11), hora (14:30, 14hs) o rango horario (14-16, 22-06). Varias palabras se combinan."
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                className="block w-full"
                            />
                        </div>

                        <div className="max-h-[580px] flex-1 divide-y divide-atalaya-border overflow-y-auto">
                            {eventosFiltrados.length === 0 && (
                                <div className="p-6 text-center font-mono text-xs text-atalaya-text-muted">
                                    Sin novedades registradas
                                    {(filtro !== TODAS || busqueda.trim()) &&
                                        ' para este filtro'}
                                    .
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
                                                {ev.hora}
                                            </span>
                                            <span
                                                className="border px-1.5 py-0.5 text-[9px] font-bold uppercase"
                                                style={estiloGrupo(ev.categoria)}
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

                                        <div className="mt-2 truncate text-[11px] text-atalaya-text-muted">
                                            {ev.ubicacion ?? 'Sin ubicación'}
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
                                    <div className="flex gap-2">
                                        {puedeAmpliar && !seleccionado.constancia && (
                                            <Link
                                                href={route('ampliaciones.create', seleccionado.id)}
                                                className="border border-atalaya-cyan px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-atalaya-cyan transition hover:bg-atalaya-cyan/10"
                                            >
                                                [ + Ampliación ]
                                            </Link>
                                        )}
                                        {puedeCorregir && (
                                            <Link
                                                href={route('erratas.create', seleccionado.id)}
                                                className="border border-atalaya-amber px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-atalaya-amber transition hover:bg-atalaya-amber/10"
                                            >
                                                [ + Fe de errata ]
                                            </Link>
                                        )}
                                    </div>
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
                                    <div>
                                        <span className="text-atalaya-text-muted">
                                            REGISTRADO:{' '}
                                        </span>
                                        <span className="text-white">
                                            {seleccionado.fecha}{' '}
                                            {seleccionado.hora} ·{' '}
                                            {seleccionado.autor ?? 'N/D'}
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
