import 'leaflet/dist/leaflet.css';
import { useEffect, useMemo } from 'react';
import {
    CircleMarker,
    MapContainer,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';

const CENTRO_VILLA_MARIA = [-32.4075, -63.2402];

// Legibles sobre los tiles claros y sobre los oscurecidos del tema marino.
const COLORES = {
    municipal: '#3b6fd0',
    provincial: '#12937f',
    seleccionado: '#e0483d',
};

function CentrarEn({ punto }) {
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
 * Mapa Leaflet + OpenStreetMap con los Puntos de Monitoreo.
 * Click en un punto -> onSelect(id). Los PM sin coordenadas no se dibujan.
 */
export default function MapaPuntosMonitoreo({
    puntos,
    seleccionadoId = null,
    onSelect,
    className = 'h-80 w-full',
}) {
    const conCoordenadas = useMemo(
        () =>
            puntos.filter((p) => p.latitud !== null && p.longitud !== null),
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
            className={'z-0 rounded-md border border-line ' + className}
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
                                : COLORES[p.jurisdiccion],
                            weight: activo ? 3 : 1,
                            fillOpacity: activo ? 0.9 : 0.6,
                        }}
                        eventHandlers={{ click: () => onSelect?.(p.id) }}
                    >
                        <Tooltip>
                            {p.codigo} · {p.nombre}
                        </Tooltip>
                    </CircleMarker>
                );
            })}

            <CentrarEn punto={seleccionado} />
        </MapContainer>
    );
}
