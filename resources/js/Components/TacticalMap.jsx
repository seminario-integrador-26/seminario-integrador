import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    Circle,
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    useMap,
} from 'react-leaflet';
import L from 'leaflet';

/**
 * Recalcula el tamaño del mapa tras el montaje y ante cualquier resize del
 * contenedor. Sin esto, Leaflet calcula las teselas antes de que el contenedor
 * flex tenga su tamaño final y quedan grises/desalineadas hasta un zoom/resize.
 */
function AjustarTamano() {
    const map = useMap();

    useEffect(() => {
        const reajustar = () => map.invalidateSize();
        const t = setTimeout(reajustar, 100);
        const observer = new ResizeObserver(reajustar);
        observer.observe(map.getContainer());

        return () => {
            clearTimeout(t);
            observer.disconnect();
        };
    }, [map]);

    return null;
}

/**
 * Centra el mapa en el PM elegido desde el buscador y abre su popup.
 * `enfoque.n` cambia en cada búsqueda para poder re-enfocar el mismo PM.
 */
function EnfocarPunto({ enfoque, marcadores }) {
    const map = useMap();

    useEffect(() => {
        if (!enfoque) {
            return;
        }

        const { punto } = enfoque;
        map.flyTo([punto.latitud, punto.longitud], 17, { duration: 0.8 });
        map.once('moveend', () =>
            marcadores.current[punto.id]?.openPopup(),
        );
    }, [enfoque, map, marcadores]);

    return null;
}

/**
 * Visor cartográfico táctico del dashboard: eventos georreferenciados sobre los
 * puntos de monitoreo. Teselas de OpenStreetMap oscurecidas por filtro CSS
 * (clase .mapa-tactico en app.css) — CLAUDE.md fija OSM vía Leaflet.
 */

// Fix de iconos por defecto de Leaflet bajo Vite.
delete L.Icon.Default.prototype._getIconUrl;

/** Color del semáforo por eje de clasificación (RN-02). */
export const COLOR_POR_CATEGORIA = {
    'seguridad pública': '#EF4444',
    'convivencia urbana': '#F59E0B',
    prevención: '#F97316',
    informativo: '#00D2FF',
};

export const colorDeCategoria = (categoria) =>
    COLOR_POR_CATEGORIA[categoria?.toLowerCase()] ?? '#00D2FF';

const iconoEvento = (color) =>
    L.divIcon({
        className: 'custom-tactical-marker',
        html: `
            <div style="
                display: flex;
                align-items: center;
                justify-content: center;
                width: 22px;
                height: 22px;
                border-radius: 50%;
                background-color: #031427;
                border: 2px solid ${color};
                box-shadow: 0 0 10px ${color}88;
            ">
                <div style="
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background-color: ${color};
                "></div>
            </div>
        `,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -12],
    });

const iconoCamara = () =>
    L.divIcon({
        className: 'custom-camera-marker',
        html: `
            <div style="
                display: flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                height: 18px;
                background-color: #031427;
                border: 1.5px solid #00D2FF;
            ">
                <div style="
                    width: 6px;
                    height: 6px;
                    background-color: #00D2FF;
                "></div>
            </div>
        `,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -10],
    });

// Centro de Villa María, Córdoba.
const CENTRO_VILLA_MARIA = [-32.4075, -63.2403];

export default function TacticalMap({
    eventos = [],
    puntosMonitoreo = [],
    eventoSeleccionado = null,
    onSeleccionarEvento = () => {},
    enfoque = null,
    puedeRegistrar = false,
}) {
    const [montado, setMontado] = useState(false);
    const marcadores = useRef({});

    useEffect(() => {
        setMontado(true);
    }, []);

    if (!montado) {
        return (
            <div className="flex h-full w-full items-center justify-center bg-atalaya-canvas font-mono text-xs text-atalaya-text-muted">
                INICIALIZANDO SUBSISTEMA CARTOGRÁFICO...
            </div>
        );
    }

    return (
        <div className="mapa-tactico relative h-full w-full overflow-hidden bg-atalaya-canvas">
            <MapContainer
                center={CENTRO_VILLA_MARIA}
                zoom={14}
                style={{
                    height: '100%',
                    width: '100%',
                    backgroundColor: '#031427',
                }}
                zoomControl={false}
            >
                <AjustarTamano />
                <EnfocarPunto enfoque={enfoque} marcadores={marcadores} />

                <TileLayer
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    maxZoom={19}
                />

                {/* Puntos de monitoreo (domos y cámaras) */}
                {puntosMonitoreo.map((pm) => (
                    <Marker
                        key={`pm-${pm.id}`}
                        position={[pm.latitud, pm.longitud]}
                        icon={iconoCamara()}
                        ref={(m) => {
                            marcadores.current[pm.id] = m;
                        }}
                    >
                        <Popup>
                            <div className="p-1 font-mono text-xs">
                                <div className="mb-0.5 font-bold uppercase tracking-wider text-atalaya-cyan">
                                    [ {pm.codigo} · {pm.nombre} ]
                                </div>
                                <div className="text-[10px] text-atalaya-text-muted">
                                    COORD: {Number(pm.latitud).toFixed(4)},{' '}
                                    {Number(pm.longitud).toFixed(4)}
                                </div>
                                <div className="text-[10px] uppercase text-atalaya-text-dim">
                                    Jurisdicción: {pm.jurisdiccion}
                                </div>
                                {puedeRegistrar && (
                                    <Link
                                        href={route('eventos.create', {
                                            punto_monitoreo_id: pm.id,
                                        })}
                                        className="mt-2 block border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-center text-[10px] font-bold uppercase tracking-wider !text-atalaya-cyan transition hover:bg-atalaya-cyan/25"
                                    >
                                        [ + Registrar evento aquí ]
                                    </Link>
                                )}
                            </div>
                        </Popup>
                    </Marker>
                ))}

                {/* Eventos georreferenciados */}
                {eventos.map((ev) => {
                    const color = colorDeCategoria(ev.categoria);
                    const seleccionado = eventoSeleccionado?.id === ev.id;

                    return (
                        <div key={`ev-${ev.id}`}>
                            <Marker
                                position={[ev.latitud, ev.longitud]}
                                icon={iconoEvento(color)}
                                eventHandlers={{
                                    click: () => onSeleccionarEvento(ev),
                                }}
                            >
                                <Popup>
                                    <div className="max-w-[220px] p-1 font-mono text-xs">
                                        <div className="mb-1 flex items-center justify-between gap-2 border-b border-atalaya-border pb-1">
                                            <span className="font-bold uppercase text-white">
                                                {ev.codigo}
                                            </span>
                                            <span
                                                className="px-1.5 text-[9px] font-bold uppercase"
                                                style={{
                                                    color,
                                                    border: `1px solid ${color}`,
                                                    backgroundColor: `${color}15`,
                                                }}
                                            >
                                                {ev.categoria}
                                            </span>
                                        </div>
                                        <div className="mb-1 font-sans text-[11px] text-white">
                                            {ev.descripcion}
                                        </div>
                                        <div className="text-[10px] text-atalaya-text-muted">
                                            LUGAR: {ev.ubicacion}
                                        </div>
                                        <div className="text-[10px] text-atalaya-text-dim">
                                            HORA: {ev.hora} · TURNO #{ev.turno_id}
                                        </div>
                                    </div>
                                </Popup>
                            </Marker>

                            {/* Perímetro del evento seleccionado */}
                            {seleccionado && (
                                <Circle
                                    center={[ev.latitud, ev.longitud]}
                                    radius={180}
                                    pathOptions={{
                                        color,
                                        fillColor: color,
                                        fillOpacity: 0.15,
                                        weight: 1,
                                        dashArray: '4, 4',
                                    }}
                                />
                            )}
                        </div>
                    );
                })}
            </MapContainer>
        </div>
    );
}
