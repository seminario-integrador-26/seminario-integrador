/**
 * Colores compartidos de los catálogos, por código.
 *
 * - Grupos de evento (GrupoEvento.codigo): mismos colores que el semáforo de
 *   TacticalMap. El color nunca es la única pista: el nombre va siempre escrito.
 * - Tipos de PM (TipoPuntoMonitoreo.codigo): mismos que MapaPuntosMonitoreo.
 */
export const COLOR_GRUPO: Record<string, string> = {
    policia: '#EF4444',
    guardia_local: '#F59E0B',
    internos: '#00D2FF',
};

export const COLOR_TIPO_PUNTO: Record<string, string> = {
    municipal: '#00D2FF',
    provincial: '#0284C7',
    alarma: '#A855F7',
};

export const colorGrupo = (codigo: string) => COLOR_GRUPO[codigo] ?? '#94A3B8';
