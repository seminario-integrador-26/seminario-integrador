import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Select from '@/Components/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { colorGrupo } from '@/catalogos';
import type { Opciones, PuntoConCoordenadas, PuntoMapa, TipoEventoOpcion } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import L from '@/lib/leafletGlobal';
import 'leaflet.heat';
import type { DivIcon, CircleMarker as LeafletCircleMarker, LatLngTuple, PathOptions } from 'leaflet';
import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import {
    CircleMarker,
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

/** PM con eventos en el período (MapaService::densidad). */
interface PuntoDensidad {
    id: number;
    codigo: string;
    nombre: string;
    /** Código de TipoPuntoMonitoreo (municipal | provincial | alarma). */
    tipo: string;
    tipo_nombre: string;
    latitud: number;
    longitud: number;
    cantidad: number;
}

/** Un evento del mapa (MapaService::eventos, US-016). */
interface EventoMapa {
    id: number;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm */
    hora: string;
    tipo: string;
    /** Código del GrupoEvento: color del marcador. */
    categoria: string;
    categoria_nombre: string;
    descripcion: string | null;
    punto_id: number;
    /** "CODIGO — Nombre" */
    punto: string;
    /** municipal | provincial | alarma: forma del marcador. */
    tipo_punto: string;
    latitud: number;
    longitud: number;
}

/** Filtros validados en MapaController, con el período ya resuelto. */
interface Filtros {
    categorias?: string[];
    tipo_evento_id?: string | number;
    /** aaaa-mm-dd */
    desde: string;
    hasta: string;
}

interface Props {
    filtros: Filtros;
    densidad: {
        puntos: PuntoDensidad[];
        total: number;
        sin_ubicacion: number;
        /** Eventos de PMs todavía sin coordenadas: no se pueden dibujar. */
        sin_coordenadas: number;
    };
    /** total puede superar items.length: se dibujan como máximo `limite`. */
    eventos: { items: EventoMapa[]; total: number; limite: number };
    puntos: PuntoMapa[];
    tipos: TipoEventoOpcion[];
    categorias: Opciones;
}

const CENTRO_VILLA_MARIA: LatLngTuple = [-32.4075, -63.2403];

// Rampa secuencial de un solo tono (cian de marca): sobre el mapa oscuro,
// más eventos = más claro.
const GRADIENTE = { 0.25: '#0b3a5c', 0.5: '#0e7fa8', 0.8: '#00d2ff', 1: '#e6fbff' };

// Radio (m) para el drill-down al hacer clic en una zona del mapa de calor.
const RADIO_DRILL_DOWN = 250;

const categoriaActiva: Record<string, string> = {
    policia: 'border-atalaya-crimson bg-atalaya-crimson/10 text-atalaya-crimson',
    guardia_local: 'border-atalaya-amber bg-atalaya-amber/10 text-atalaya-amber',
    internos: 'border-atalaya-cyan bg-atalaya-cyan/10 text-atalaya-cyan',
};

/** Estilo del círculo según el tipo de PM (municipal | provincial | alarma). */
const ESTILO_TIPO_PUNTO: Record<string, PathOptions> = {
    municipal: { color: '#00d2ff', weight: 2, fillColor: '#00d2ff', fillOpacity: 0.45 },
    provincial: { color: '#ffffff', weight: 2, dashArray: '3 3', fillColor: '#ffffff', fillOpacity: 0.12 },
    alarma: { color: '#a855f7', weight: 2, fillColor: '#a855f7', fillOpacity: 0.35 },
};

/**
 * US-016: forma del marcador según el tipo de PM y color según la categoría.
 * La forma distingue municipal / provincial / alarma sin usar color (el cian
 * ya es de Internos y del mapa de calor).
 */
const FORMA_PUNTO: Record<string, string> = {
    municipal: '<circle cx="8" cy="8" r="6" />',
    provincial: '<rect x="3.5" y="3.5" width="9" height="9" transform="rotate(45 8 8)" />',
    alarma: '<polygon points="8,1.5 14.5,13.5 1.5,13.5" />',
};

/** SVG del marcador (también se usa en la leyenda). */
const svgMarcador = (tipoPunto: string, color: string) =>
    `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="${color}" stroke="#031427" stroke-width="1.5">${FORMA_PUNTO[tipoPunto] ?? FORMA_PUNTO.municipal}</svg>`;

const iconos = new Map<string, DivIcon>();

/** Un DivIcon por combinación forma/color (no uno por evento). */
const iconoEvento = (tipoPunto: string, categoria: string) => {
    const clave = `${tipoPunto}:${categoria}`;

    if (!iconos.has(clave)) {
        iconos.set(
            clave,
            L.divIcon({
                className: 'marcador-evento',
                html: svgMarcador(tipoPunto, colorGrupo(categoria)),
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                popupAnchor: [0, -8],
            }),
        );
    }

    return iconos.get(clave) as DivIcon;
};

/** Hasta 10 marcadores por anillo alrededor del PM. */
const POR_ANILLO = 10;
/** Separación entre anillos, en grados de latitud (~13 m). */
const PASO_ANILLO = 0.00012;

/**
 * Posición de cada evento: varios en el mismo PM se abren en anillos a su
 * alrededor; si no, quedarían apilados y solo se podría clicar el de arriba.
 */
function posiciones(eventos: EventoMapa[]): Map<number, LatLngTuple> {
    const porPunto = new Map<number, EventoMapa[]>();
    eventos.forEach((e) => porPunto.set(e.punto_id, [...(porPunto.get(e.punto_id) ?? []), e]));

    const resultado = new Map<number, LatLngTuple>();
    porPunto.forEach((delPunto) => {
        delPunto.forEach((e, i) => {
            if (delPunto.length === 1) {
                resultado.set(e.id, [e.latitud, e.longitud]);
                return;
            }

            const anillo = Math.floor(i / POR_ANILLO);
            const enAnillo = Math.min(POR_ANILLO, delPunto.length - anillo * POR_ANILLO);
            const angulo = (2 * Math.PI * (i % POR_ANILLO)) / enAnillo;
            const radio = PASO_ANILLO * (anillo + 1);
            // La longitud se corrige por la latitud para que el anillo sea redondo.
            const escala = Math.cos((e.latitud * Math.PI) / 180);

            resultado.set(e.id, [
                e.latitud + radio * Math.sin(angulo),
                e.longitud + (radio * Math.cos(angulo)) / escala,
            ]);
        });
    });

    return resultado;
}

/** US-016: un marcador por evento con su resumen en el popup. */
function MarcadoresEventos({ eventos }: { eventos: EventoMapa[] }) {
    const ubicaciones = useMemo(() => posiciones(eventos), [eventos]);

    return (
        <>
            {eventos.map((e) => (
                <Marker
                    key={`ev-${e.id}`}
                    position={ubicaciones.get(e.id) ?? [e.latitud, e.longitud]}
                    icon={iconoEvento(e.tipo_punto, e.categoria)}
                >
                    <Popup>
                        <div className="max-w-[240px] font-mono text-xs">
                            <div className="font-bold text-white">{e.tipo}</div>
                            <div className="text-[10px] font-bold uppercase" style={{ color: colorGrupo(e.categoria) }}>
                                {e.categoria_nombre}
                            </div>
                            <div className="mt-1 text-atalaya-text-muted">
                                {e.fecha} · {e.hora} hs
                            </div>
                            {e.descripcion && (
                                <div className="mt-1 line-clamp-4 font-sans text-[11px] text-white">{e.descripcion}</div>
                            )}
                            <div className="mt-1 text-[10px] text-atalaya-text-dim">PM {e.punto}</div>
                            <Link
                                href={route('eventos.show', e.id)}
                                className="mt-2 block border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-center text-[10px] font-bold uppercase !text-atalaya-cyan hover:bg-atalaya-cyan/25"
                            >
                                Ver detalle
                            </Link>
                        </div>
                    </Popup>
                </Marker>
            ))}
        </>
    );
}

/** Capa de calor (leaflet.heat); se rehace cuando cambian los datos. */
function CapaCalor({ puntos }: { puntos: PuntoDensidad[] }) {
    const map = useMap();

    useEffect(() => {
        // leaflet.heat falla (getImageData) si el mapa todavía mide 0 px.
        const { x, y } = map.getSize();
        if (puntos.length === 0 || x === 0 || y === 0) {
            return undefined;
        }

        const max = Math.max(...puntos.map((p) => p.cantidad));
        const capa = L.heatLayer(
            puntos.map((p) => [p.latitud, p.longitud, p.cantidad / max]),
            // maxZoom: desde ese zoom la intensidad es completa; con uno mayor
            // al inicial (14) el calor se ve apagado al abrir el mapa.
            { radius: 30, blur: 20, maxZoom: 13, max: 1, minOpacity: 0.4, gradient: GRADIENTE },
        ).addTo(map);

        return () => {
            map.removeLayer(capa);
        };
    }, [map, puntos]);

    return null;
}

/** Recalcula el tamaño del mapa cuando el contenedor cambia. */
function AjustarTamano() {
    const map = useMap();

    useEffect(() => {
        const observer = new ResizeObserver(() => map.invalidateSize());
        observer.observe(map.getContainer());

        return () => observer.disconnect();
    }, [map]);

    return null;
}

/**
 * US-017 drill-down: clic en una zona -> PM con eventos más cercano (dentro
 * del radio) y se abre su detalle.
 */
function DrillDownPorZona({
    puntos,
    onElegir,
}: {
    puntos: PuntoDensidad[];
    onElegir: (punto: PuntoDensidad) => void;
}) {
    const map = useMapEvents({
        click(e) {
            let elegido = null as PuntoDensidad | null;
            let mejor = RADIO_DRILL_DOWN;

            puntos.forEach((p) => {
                const d = map.distance(e.latlng, [p.latitud, p.longitud]);
                if (d <= mejor) {
                    mejor = d;
                    elegido = p;
                }
            });

            if (elegido) {
                onElegir(elegido);
            }
        },
    });

    return null;
}

function Interruptor({
    activo,
    onChange,
    children,
}: {
    activo: boolean;
    onChange: (activo: boolean) => void;
    children: ReactNode;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-2 text-xs text-atalaya-text-muted">
            <input
                type="checkbox"
                checked={activo}
                onChange={(e) => onChange(e.target.checked)}
                className="rounded-none border-atalaya-border bg-atalaya-canvas text-atalaya-cyan focus:ring-atalaya-cyan"
            />
            {children}
        </label>
    );
}

export default function Index({ filtros, densidad, eventos, puntos, tipos, categorias }: Props) {
    // eventos = un marcador por evento (US-016); puntos = PMs con su cantidad.
    const [capas, setCapas] = useState({ calor: true, eventos: true, puntos: false, todos: false });
    // `n` cambia en cada clic para re-abrir el popup del mismo PM.
    const [elegido, setElegido] = useState<(PuntoDensidad & { n: number }) | null>(null);
    const marcadores = useRef<Record<number, LeafletCircleMarker | null>>({});

    // Capa de referencia: los PM sin coordenadas no se dibujan (irían a 0, 0).
    const puntosConCoordenadas = useMemo(
        () =>
            puntos.filter(
                (p): p is PuntoConCoordenadas =>
                    p.latitud !== null && p.longitud !== null,
            ),
        [puntos],
    );

    const filtrar = (cambios: Partial<Filtros>) =>
        router.get(
            route('mapa.index'),
            { ...filtros, ...cambios },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const seleccionadas = filtros.categorias ?? [];
    const alternarCategoria = (clave: string) =>
        filtrar({
            categorias: seleccionadas.includes(clave)
                ? seleccionadas.filter((c) => c !== clave)
                : [...seleccionadas, clave],
        });

    const max = useMemo(
        () => Math.max(1, ...densidad.puntos.map((p) => p.cantidad)),
        [densidad.puntos],
    );

    // Abre el popup del PM elegido por clic en una zona del mapa de calor.
    useEffect(() => {
        if (elegido) {
            marcadores.current[elegido.id]?.openPopup();
        }
    }, [elegido]);

    // Drill-down a la consulta de eventos manteniendo los filtros del mapa.
    const verEventos = (punto: PuntoDensidad) =>
        route('eventos.index', {
            punto_monitoreo_id: punto.id,
            desde: filtros.desde,
            hasta: filtros.hasta,
            categorias: seleccionadas,
            tipo_evento_id: filtros.tipo_evento_id,
        });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-mono text-sm font-bold uppercase tracking-widest text-white">
                    Mapa de eventos
                </h2>
            }
        >
            <Head title="Mapa de eventos" />

            <div className="flex flex-col gap-4 p-4 lg:h-[calc(100vh-7.5rem)] lg:flex-row">
                <aside className="space-y-4 border border-atalaya-border bg-atalaya-surface p-4 lg:w-80 lg:shrink-0 lg:overflow-y-auto">
                    <div>
                        <InputLabel value="Categoría" />
                        <div className="mt-1 flex flex-wrap gap-2">
                            {Object.entries(categorias)
                                .map(([clave, etiqueta]) => {
                                    const activa = seleccionadas.includes(clave);

                                    return (
                                        <button
                                            key={clave}
                                            type="button"
                                            aria-pressed={activa}
                                            onClick={() => alternarCategoria(clave)}
                                            className={
                                                'border px-2 py-1 font-mono text-[10px] font-bold uppercase ' +
                                                (activa
                                                    ? categoriaActiva[clave]
                                                    : 'border-atalaya-border text-atalaya-text-dim hover:text-white')
                                            }
                                        >
                                            {etiqueta}
                                        </button>
                                    );
                                })}
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="tipo_evento_id" value="Tipo" />
                        <Select
                            id="tipo_evento_id"
                            buscable
                            className="mt-1"
                            value={filtros.tipo_evento_id ?? ''}
                            onChange={(e) => filtrar({ tipo_evento_id: e.target.value || undefined })}
                        >
                            <option value="">Todos</option>
                            {tipos.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.nombre}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel htmlFor="desde" value="Desde" />
                            <TextInput
                                id="desde"
                                type="date"
                                className="mt-1 block w-full"
                                value={filtros.desde}
                                onChange={(e) => e.target.value && filtrar({ desde: e.target.value })}
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="hasta" value="Hasta" />
                            <TextInput
                                id="hasta"
                                type="date"
                                className="mt-1 block w-full"
                                value={filtros.hasta}
                                onChange={(e) => e.target.value && filtrar({ hasta: e.target.value })}
                            />
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-atalaya-border pt-4">
                        <InputLabel value="Capas" />
                        <Interruptor
                            activo={capas.calor}
                            onChange={(v) => setCapas((c) => ({ ...c, calor: v }))}
                        >
                            Mapa de calor
                        </Interruptor>
                        <Interruptor
                            activo={capas.eventos}
                            onChange={(v) => setCapas((c) => ({ ...c, eventos: v }))}
                        >
                            Eventos (uno por marcador)
                        </Interruptor>
                        <Interruptor
                            activo={capas.puntos}
                            onChange={(v) => setCapas((c) => ({ ...c, puntos: v }))}
                        >
                            Puntos con eventos (cantidad)
                        </Interruptor>
                        <Interruptor
                            activo={capas.todos}
                            onChange={(v) => setCapas((c) => ({ ...c, todos: v }))}
                        >
                            Todos los PMs (referencia)
                        </Interruptor>
                    </div>

                    <div className="space-y-2 border-t border-atalaya-border pt-4 text-xs text-atalaya-text-muted">
                        <p>
                            <span className="font-mono text-lg font-bold text-white">
                                {densidad.total}
                            </span>{' '}
                            eventos en {densidad.puntos.length} PM.
                        </p>
                        <p className="text-atalaya-text-dim">
                            No se incluyen {densidad.sin_ubicacion} evento(s)
                            sin ubicación ni los de tipos no cuantificables.
                        </p>
                        {eventos.total > eventos.items.length && (
                            <p className="text-atalaya-amber">
                                Se dibujan los {eventos.items.length} eventos más
                                recientes de {eventos.total}: acotá el período
                                o la categoría para verlos todos.
                            </p>
                        )}
                        {densidad.sin_coordenadas > 0 && (
                            <p className="text-atalaya-amber">
                                Tampoco {densidad.sin_coordenadas} evento(s) de
                                PMs sin coordenadas: cargalas en Puntos de
                                monitoreo para verlos en el mapa.
                            </p>
                        )}

                        <div className="space-y-1.5 pt-2">
                            <p className="font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Referencias · eventos
                            </p>
                            {Object.entries(categorias).map(([clave, nombre]) => (
                                <p key={clave} className="flex items-center gap-2">
                                    <span
                                        className="inline-block h-3 w-3 rounded-full"
                                        style={{ background: colorGrupo(clave) }}
                                    />
                                    {nombre}
                                </p>
                            ))}
                            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-atalaya-text-dim">
                                {[
                                    ['municipal', 'Municipal'],
                                    ['provincial', 'Provincial'],
                                    ['alarma', 'Alarma'],
                                ].map(([tipo, nombre]) => (
                                    <span key={tipo} className="flex items-center gap-1">
                                        <span dangerouslySetInnerHTML={{ __html: svgMarcador(tipo, '#94A3B8') }} />
                                        {nombre}
                                    </span>
                                ))}
                            </p>
                            <p className="pt-2 font-mono text-[10px] font-bold uppercase tracking-widest text-atalaya-text-dim">
                                Referencias · puntos con eventos
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-atalaya-cyan bg-atalaya-cyan/70" />
                                PM municipal
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-dashed border-white bg-transparent" />
                                PM provincial
                            </p>
                            <p className="flex items-center gap-2">
                                <span className="inline-block h-3 w-3 rounded-full border-2 border-[#a855f7] bg-[#a855f7]/50" />
                                Alarma comunitaria
                            </p>
                            <p className="text-atalaya-text-dim">
                                El tamaño del círculo indica la cantidad de
                                eventos. Clic en una zona o en un punto para
                                ver sus eventos.
                            </p>
                            <div className="pt-1">
                                <div
                                    className="h-2 w-full"
                                    style={{
                                        background:
                                            'linear-gradient(to right, #0b3a5c, #0e7fa8, #00d2ff, #e6fbff)',
                                    }}
                                />
                                <div className="mt-0.5 flex justify-between text-[10px] text-atalaya-text-dim">
                                    <span>Menos</span>
                                    <span>Más eventos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <div className="mapa-tactico relative h-[60vh] border border-atalaya-border lg:h-auto lg:flex-1">
                    <MapContainer
                        center={CENTRO_VILLA_MARIA}
                        zoom={14}
                        className="h-full w-full"
                        preferCanvas
                    >
                        <TileLayer
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        />
                        <AjustarTamano />

                        {capas.todos &&
                            puntosConCoordenadas.map((p) => (
                                <CircleMarker
                                    key={`ref-${p.id}`}
                                    center={[p.latitud, p.longitud]}
                                    radius={2.5}
                                    pathOptions={{
                                        color: '#94a3b8',
                                        weight: 0,
                                        fillOpacity: 0.6,
                                    }}
                                />
                            ))}

                        {capas.calor && <CapaCalor puntos={densidad.puntos} />}

                        {capas.puntos &&
                            densidad.puntos.map((p) => {
                                return (
                                    <CircleMarker
                                        key={p.id}
                                        ref={(m) => {
                                            marcadores.current[p.id] = m;
                                        }}
                                        center={[p.latitud, p.longitud]}
                                        radius={5 + Math.sqrt(p.cantidad / max) * 12}
                                        pathOptions={ESTILO_TIPO_PUNTO[p.tipo] ?? ESTILO_TIPO_PUNTO.municipal}
                                    >
                                        <Popup>
                                            <div className="font-mono text-xs">
                                                <div className="font-bold text-atalaya-cyan">
                                                    {p.codigo}
                                                </div>
                                                <div className="text-atalaya-text-muted">
                                                    {p.nombre}
                                                </div>
                                                <div className="text-[10px] uppercase text-atalaya-text-dim">
                                                    PM {p.tipo_nombre}
                                                </div>
                                                <div className="mt-1 text-white">
                                                    {p.cantidad} evento(s) en el período
                                                </div>
                                                <Link
                                                    href={verEventos(p)}
                                                    className="mt-2 block border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-center text-[10px] font-bold uppercase !text-atalaya-cyan hover:bg-atalaya-cyan/25"
                                                >
                                                    Ver eventos
                                                </Link>
                                            </div>
                                        </Popup>
                                    </CircleMarker>
                                );
                            })}

                        {capas.eventos && <MarcadoresEventos eventos={eventos.items} />}

                        <DrillDownPorZona
                            puntos={densidad.puntos}
                            onElegir={(p) => {
                                setCapas((c) => ({ ...c, puntos: true }));
                                setElegido({ ...p, n: Date.now() });
                            }}
                        />
                    </MapContainer>

                    {densidad.puntos.length === 0 && (
                        <div className="pointer-events-none absolute inset-x-0 top-4 z-[500] mx-auto w-fit border border-atalaya-border bg-atalaya-surface px-4 py-2 font-mono text-xs text-atalaya-text-muted">
                            Sin eventos con ubicación para estos filtros.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
