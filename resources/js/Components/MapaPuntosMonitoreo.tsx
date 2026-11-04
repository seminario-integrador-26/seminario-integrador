import { COLOR_TIPO_PUNTO, TIPOS_PUNTO } from '@/catalogos';
import type { PuntoConCoordenadas, PuntoMapa } from '@/types';
import type { LatLngTuple } from 'leaflet';
import { useEffect, useMemo } from 'react';
import {
    CircleMarker,
    MapContainer,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';

const CENTRO_VILLA_MARIA: LatLngTuple = [-32.4075, -63.2402];

// Colores por tipo de PM (@/catalogos) y naranja de alta visibilidad para el
// punto seleccionado.
const COLORES: Record<string, string> = {
    ...COLOR_TIPO_PUNTO,
    seleccionado: '#F97316',
};

function CentrarEn({ punto }: { punto: PuntoConCoordenadas | null }) {
    const map = useMap();

    useEffect(() => {
        if (punto) {
            map.flyTo(
                [punto.latitud, punto.longitud],
                Math.max(map.getZoom(), 16),
            );
        }
    }, [punto, map]);

    return null;
}

/**
 * Recalcula el tamaño del mapa tras el montaje y ante cualquier resize: sin
 * esto Leaflet dibuja las teselas antes de que el contenedor tenga su tamaño
 * final y quedan grises/desalineadas hasta un zoom/resize.
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
 * Mapa Leaflet + OpenStreetMap con los Puntos de Monitoreo.
 * Click en un punto -> onSelect(id). Los PM sin coordenadas no se dibujan.
 */
export default function MapaPuntosMonitoreo({
    puntos,
    seleccionadoId = null,
    onSelect,
    className = 'h-80 w-full',
}: {
    puntos: PuntoMapa[];
    seleccionadoId?: number | null;
    onSelect?: (id: number) => void;
    className?: string;
}) {
    const conCoordenadas = useMemo(
        () =>
            puntos.filter(
                (p): p is PuntoConCoordenadas =>
                    p.latitud !== null && p.longitud !== null,
            ),
        [puntos],
    );

    const seleccionado =
        conCoordenadas.find((p) => p.id === seleccionadoId) ?? null;

    // El seleccionado se dibuja último para que quede por encima del resto.
    const ordenados = seleccionado
        ? [...conCoordenadas.filter((p) => p !== seleccionado), seleccionado]
        : conCoordenadas;

    return (
        <MapContainer
            center={CENTRO_VILLA_MARIA}
            zoom={13}
            scrollWheelZoom={false}
            className={'mapa-tactico z-0 rounded-none ' + className}
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />

            {ordenados.map((p) => {
                const activo = p === seleccionado;

                return (
                    <CircleMarker
                        key={p.id}
                        center={[p.latitud, p.longitud]}
                        radius={activo ? 10 : 5}
                        pathOptions={{
                            color: activo
                                ? COLORES.seleccionado
                                : COLORES[p.tipo],
                            weight: activo ? 3 : 1,
                            fillOpacity: activo ? 0.9 : 0.6,
                        }}
                        eventHandlers={{ click: () => onSelect?.(p.id) }}
                    >
                        <Tooltip>
                            {p.codigo} — {p.nombre}
                        </Tooltip>
                    </CircleMarker>
                );
            })}

            <AjustarTamano />
            <CentrarEn punto={seleccionado} />
        </MapContainer>
    );
}

const LEYENDA = [...TIPOS_PUNTO, ['seleccionado', 'Seleccionado']] as const;

/**
 * Título + leyenda de colores para poner arriba del mapa en los formularios
 * de registro y de fe de errata.
 */
export function EncabezadoMapaPuntos() {
    return (
        <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2 font-mono text-[10px] uppercase tracking-widest text-atalaya-text-dim">
            <span>Mapa de puntos de monitoreo</span>
            <span className="space-x-3">
                {LEYENDA.map(([clave, etiqueta]) => (
                    <span key={clave}>
                        <span style={{ color: COLORES[clave] }}>●</span>{' '}
                        {etiqueta}
                    </span>
                ))}
            </span>
        </div>
    );
}
