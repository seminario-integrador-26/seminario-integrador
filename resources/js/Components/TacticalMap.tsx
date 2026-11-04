import type { PuntoConCoordenadas, PuntoMapa } from '@/types';
import { colorTipoPunto } from '@/catalogos';
import { Link } from '@inertiajs/react';
import type { LatLngTuple, Marker as LeafletMarker } from 'leaflet';
import { type MutableRefObject, useEffect, useMemo, useRef, useState } from 'react';
import {
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    useMap,
} from 'react-leaflet';
import L from 'leaflet';

/** PM a enfocar; `n` cambia en cada búsqueda para re-enfocar el mismo PM. */
export interface Enfoque {
    punto: PuntoMapa;
    n: number;
}

type Marcadores = MutableRefObject<Record<number, LeafletMarker | null>>;

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
function EnfocarPunto({ enfoque, marcadores }: { enfoque: Enfoque | null; marcadores: Marcadores }) {
    const map = useMap();

    useEffect(() => {
        if (!enfoque) {
            return;
        }

        const { punto } = enfoque;
        // Un PM sin coordenadas no está en el mapa: no hay adónde ir.
        if (punto.latitud === null || punto.longitud === null) {
            return;
        }

        map.flyTo([punto.latitud, punto.longitud], 17, { duration: 0.8 });
        map.once('moveend', () =>
            marcadores.current[punto.id]?.openPopup(),
        );
    }, [enfoque, map, marcadores]);

    return null;
}

/**
 * Visor cartográfico táctico del panel: solo los puntos de monitoreo, por tipo.
 * Las novedades del libro de guardia no se dibujan acá: se ven en el mapa de
 * calor (Mapa/Index). Teselas de OpenStreetMap oscurecidas por filtro CSS
 * (clase .mapa-tactico en app.css) — CLAUDE.md fija OSM vía Leaflet.
 */

// Fix de iconos por defecto de Leaflet bajo Vite.
delete (L.Icon.Default.prototype as { _getIconUrl?: unknown })._getIconUrl;

/** Marcador del PM, con el color de su tipo (municipal / provincial / alarma). */
const iconoCamara = (tipo: string) =>
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
                border: 1.5px solid ${colorTipoPunto(tipo)};
            ">
                <div style="
                    width: 6px;
                    height: 6px;
                    background-color: ${colorTipoPunto(tipo)};
                "></div>
            </div>
        `,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -10],
    });

// Centro de Villa María, Córdoba.
const CENTRO_VILLA_MARIA: LatLngTuple = [-32.4075, -63.2403];

export default function TacticalMap({
    puntosMonitoreo = [],
    enfoque = null,
    puedeRegistrar = false,
}: {
    puntosMonitoreo?: PuntoMapa[];
    enfoque?: Enfoque | null;
    puedeRegistrar?: boolean;
}) {
    const [montado, setMontado] = useState(false);
    const marcadores: Marcadores = useRef({});

    // Los PM sin coordenadas no se dibujan (Leaflet los pondría en 0, 0).
    const puntosConCoordenadas = useMemo(
        () =>
            puntosMonitoreo.filter(
                (pm): pm is PuntoConCoordenadas =>
                    pm.latitud !== null && pm.longitud !== null,
            ),
        [puntosMonitoreo],
    );

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
                {puntosConCoordenadas.map((pm) => (
                    <Marker
                        key={`pm-${pm.id}`}
                        position={[pm.latitud, pm.longitud]}
                        icon={iconoCamara(pm.tipo)}
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
                                    Tipo: {pm.tipo}
                                    {pm.barrio && ` · B° ${pm.barrio}`}
                                </div>
                                {puedeRegistrar && (
                                    <Link
                                        href={route('eventos.create', {
                                            punto_monitoreo_id: pm.id,
                                        })}
                                        className="mt-2 block border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-1 text-center text-[10px] font-bold uppercase tracking-wider !text-atalaya-cyan transition hover:bg-atalaya-cyan/25"
                                    >
                                        [ + Registrar novedad aquí ]
                                    </Link>
                                )}
                            </div>
                        </Popup>
                    </Marker>
                ))}

            </MapContainer>
        </div>
    );
}
