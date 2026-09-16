import { useEffect, useState } from 'react';
import { Circle, MapContainer, Marker, Popup, TileLayer } from 'react-leaflet';
import L from 'leaflet';

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
}) {
    const [montado, setMontado] = useState(false);

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
