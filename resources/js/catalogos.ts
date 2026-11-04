/**
 * Colores compartidos de los catálogos, por código.
 *
 * - Grupos de evento (GrupoEvento.codigo): única fuente del semáforo (mapa,
 *   Dashboard, estadísticas). El color nunca es la única pista: el nombre va siempre escrito.
 * - Tipos de PM (TipoPuntoMonitoreo.codigo): mismos que MapaPuntosMonitoreo.
 */
export const COLOR_GRUPO: Record<string, string> = {
    policia: '#38BDF8',
    guardia_local: '#F97316',
    internos: '#E2E8F0',
};

export const COLOR_TIPO_PUNTO: Record<string, string> = {
    municipal: '#00D2FF',
    provincial: '#0284C7',
    alarma: '#A855F7',
};

export const colorGrupo = (codigo: string) => COLOR_GRUPO[codigo] ?? '#94A3B8';

/**
 * Color de un grupo a partir de su código o de su nombre ("Guardia Local" →
 * guardia_local, "Policía" → policia): el Dashboard manda el nombre y el mapa
 * el código.
 */
export const colorDeGrupo = (grupo: string | null | undefined) =>
    colorGrupo(
        (grupo ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '_'),
    );

/** Borde, texto y fondo tenue del color del grupo (badges y filtros activos). */
export const estiloGrupo = (grupo: string | null | undefined) => {
    const color = colorDeGrupo(grupo);

    return { color, borderColor: color, backgroundColor: `${color}1A` };
};
