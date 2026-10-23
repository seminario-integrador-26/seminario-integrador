/**
 * Tipos compartidos del frontend. Reflejan lo que arma Laravel: si cambia un
 * controller o HandleInertiaRequests::share(), actualizar acá.
 */

export type Rol = 'Supervisor' | 'Administrativo' | 'Administrador de sistema';

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    email_verified_at: string | null;
}

/** Props que Inertia comparte con todas las páginas (HandleInertiaRequests::share). */
export interface SharedProps {
    auth: {
        user: User | null;
        roles: Rol[];
        permissions: string[];
    };
    notificacionesFallidas: number;
    flash: {
        success: string | null;
        error: string | null;
        /** US-003: id del evento recién registrado. */
        eventoRegistrado: number | null;
        /** US-024: "Fila N: motivo" de una importación de PMs rechazada. */
        erroresImportacion: string[] | null;
    };
    errors: Record<string, string>;
}

/** Props de una página: las propias más las compartidas. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & SharedProps;

/** Punto de monitoreo para mapas y buscadores (PuntoMonitoreo::paraMapa). */
export interface PuntoMapa {
    id: number;
    codigo: string;
    nombre: string;
    /** Código del TipoPuntoMonitoreo (clave de `tiposPunto`). */
    tipo: string;
    barrio: string | null;
    latitud: number | null;
    longitud: number | null;
}

/** PM con coordenadas: el único que se puede dibujar en un mapa. */
export type PuntoConCoordenadas = PuntoMapa & { latitud: number; longitud: number };

/** Evento del feed del Panel (DashboardService::eventosDelTurno). */
export interface EventoPanel {
    id: number;
    /** AT-<año>-<id con 4 dígitos> */
    codigo: string;
    /** Nombre del GrupoEvento. */
    categoria: string;
    es_cuantificable: boolean;
    tipo: string;
    /** "CODIGO — Nombre" o null si no tiene ubicación. */
    ubicacion: string | null;
    latitud: number | null;
    longitud: number | null;
    /** dd/mm/aaaa */
    fecha: string;
    /** hh:mm:ss */
    hora: string;
    timestamp_video: string | null;
    descripcion: string | null;
    autor: string | null;
}

/** codigo => nombre (GrupoEvento::opciones, TipoPuntoMonitoreo::opciones). */
export type Opciones = Record<string, string>;

/** Tipo de PM tal como lo lista PuntoMonitoreoController::catalogos. */
export interface TipoPuntoItem {
    id: number;
    codigo: string;
    nombre: string;
}

/** Ítem de catálogo simple (barrios, cuadrantes). */
export interface ItemCatalogo {
    id: number;
    nombre: string;
}

/** Tipo de evento para selectores (EventoService::tiposEvento). */
export interface TipoEventoOpcion {
    id: number;
    nombre: string;
    /** Código del GrupoEvento (clave de `categorias`). */
    categoria: string;
    requiere_ubicacion: boolean;
    /** Apertura / Cierre de turno: sin PM ni timestamp del video. */
    sin_camara: boolean;
}

export type EstadoNotificacion = 'pendiente' | 'enviada' | 'fallida';

/** Envío a un grupo (EventoController::notificaciones). */
export interface Notificacion {
    id: number;
    grupo: string | null;
    estado: EstadoNotificacion;
    intentos: number;
    error: string | null;
}

/** Paginador de Laravel tal como lo serializa a JSON (LengthAwarePaginator). */
export interface Paginador<T = unknown> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
