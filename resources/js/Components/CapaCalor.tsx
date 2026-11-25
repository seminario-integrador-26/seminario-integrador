import L from '@/lib/leafletGlobal';
import 'leaflet.heat';
import type { LatLngTuple } from 'leaflet';
import { useEffect } from 'react';
import { useMap, useMapEvents } from 'react-leaflet';

/** Lo mínimo para dibujar calor: un PM con su cantidad de novedades. */
export interface PuntoCalor {
    latitud: number;
    longitud: number;
    cantidad: number;
}

export const CENTRO_VILLA_MARIA: LatLngTuple = [-32.4075, -63.2403];

// Rampa secuencial de un solo tono (cian de marca): sobre el mapa oscuro,
// más novedades = más claro.
export const GRADIENTE = { 0.25: '#0b3a5c', 0.5: '#0e7fa8', 0.8: '#00d2ff', 1: '#e6fbff' };

/** Radio (m) para el drill-down al hacer clic en una zona del mapa de calor. */
export const RADIO_DRILL_DOWN = 250;

/** Capa de calor (leaflet.heat); se rehace cuando cambian los datos. */
export function CapaCalor({ puntos }: { puntos: PuntoCalor[] }) {
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
export function AjustarTamano() {
    const map = useMap();

    useEffect(() => {
        const observer = new ResizeObserver(() => map.invalidateSize());
        observer.observe(map.getContainer());

        return () => observer.disconnect();
    }, [map]);

    return null;
}

/**
 * Drill-down: clic en una zona -> PM con novedades más cercano (dentro del radio).
 */
export function DrillDownPorZona<T extends PuntoCalor>({
    puntos,
    onElegir,
}: {
    puntos: T[];
    onElegir: (punto: T) => void;
}) {
    const map = useMapEvents({
        click(e) {
            let elegido = null as T | null;
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
