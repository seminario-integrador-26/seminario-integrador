import type { Paginador } from '@/types';
import { Link } from '@inertiajs/react';
import type { CSSProperties, MouseEventHandler, ReactNode } from 'react';

type Alineacion = 'izquierda' | 'derecha' | 'centro';
type Hijos = { children?: ReactNode };

/**
 * Piezas de tabla compartidas: TODAS las tablas del sistema las usan para
 * verse y comportarse igual (tipografía, espaciado, acciones, paginación).
 */

/** Contenedor con borde y scroll horizontal; título opcional arriba. */
export function Tabla({ titulo, acciones, children }: Hijos & { titulo?: ReactNode; acciones?: ReactNode }) {
    return (
        <div className="overflow-hidden border border-atalaya-border bg-atalaya-surface">
            {(titulo || acciones) && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-atalaya-border px-4 py-3">
                    {titulo && (
                        <span className="font-mono text-xs font-bold uppercase tracking-widest text-white">
                            {titulo}
                        </span>
                    )}
                    {acciones}
                </div>
            )}
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-atalaya-border">{children}</table>
            </div>
        </div>
    );
}

export function Cabecera({ children }: Hijos) {
    return (
        <thead className="bg-atalaya-canvas">
            <tr>{children}</tr>
        </thead>
    );
}

export function Cuerpo({ children }: Hijos) {
    return <tbody className="divide-y divide-atalaya-border">{children}</tbody>;
}

const alineacion: Record<Alineacion, string> = { izquierda: 'text-left', derecha: 'text-right', centro: 'text-center' };

/**
 * Encabezado de columna. Con `orden` es ordenable: muestra ▲/▼ en la columna
 * activa y ↕ en las demás.
 */
interface EncabezadoProps<O extends string> extends Hijos {
    alinear?: Alineacion;
    orden?: O;
    ordenActual?: O;
    direccion?: 'asc' | 'desc';
    onOrdenar?: (orden: O) => void;
}

export function Encabezado<O extends string>({
    children,
    alinear = 'izquierda',
    orden,
    ordenActual,
    direccion,
    onOrdenar,
}: EncabezadoProps<O>) {
    const activa = orden !== undefined && orden === ordenActual;

    return (
        <th
            scope="col"
            aria-sort={activa ? (direccion === 'asc' ? 'ascending' : 'descending') : undefined}
            className={`whitespace-nowrap px-4 py-3 font-mono text-[10px] font-bold uppercase tracking-wider text-atalaya-text-muted ${alineacion[alinear]}`}
        >
            {orden !== undefined ? (
                <button
                    type="button"
                    onClick={() => onOrdenar?.(orden)}
                    className={`inline-flex items-center gap-1 uppercase hover:text-white ${activa ? 'text-atalaya-cyan' : ''}`}
                >
                    {children}
                    <span aria-hidden="true">{activa ? (direccion === 'asc' ? '▲' : '▼') : '↕'}</span>
                </button>
            ) : (
                children
            )}
        </th>
    );
}

/** Fila; `onClick` la vuelve clickeable (abre el detalle). */
export function Fila({ children, onClick }: Hijos & { onClick?: MouseEventHandler<HTMLTableRowElement> }) {
    return (
        <tr
            onClick={onClick}
            className={`align-top ${onClick ? 'cursor-pointer transition-colors hover:bg-atalaya-canvas' : ''}`}
        >
            {children}
        </tr>
    );
}

/**
 * Celda. `tono`: principal (blanco, dato clave), normal o tenue.
 * `mono` para códigos, horas y números.
 */
interface CeldaProps extends Hijos {
    alinear?: Alineacion;
    tono?: 'principal' | 'normal' | 'tenue';
    mono?: boolean;
    nowrap?: boolean;
    className?: string;
}

export function Celda({ children, alinear = 'izquierda', tono = 'normal', mono = false, nowrap = false, className = '' }: CeldaProps) {
    const color = { principal: 'text-white', normal: 'text-atalaya-text-muted', tenue: 'text-atalaya-text-dim' }[tono];

    return (
        <td
            className={`px-4 py-3 text-sm ${color} ${alineacion[alinear]} ${mono ? 'font-mono' : ''} ${nowrap ? 'whitespace-nowrap' : ''} ${className}`}
        >
            {children}
        </td>
    );
}

export function FilaVacia({ columnas, children }: Hijos & { columnas: number }) {
    return (
        <tr>
            <td colSpan={columnas} className="px-4 py-6 text-center text-sm text-atalaya-text-dim">
                {children}
            </td>
        </tr>
    );
}

/** Celda de acciones: siempre la última columna, alineada a la derecha. */
export function CeldaAcciones({ children }: Hijos) {
    return (
        <td className="whitespace-nowrap px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-end gap-1">{children}</div>
        </td>
    );
}

const ICONOS = {
    editar: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125',
    eliminar: 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
    ver: 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    descargar: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3',
    reenviar: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
};

type Icono = keyof typeof ICONOS;
type Variante = 'normal' | 'peligro' | 'advertencia';

const VARIANTES: Record<Variante, string> = {
    normal: 'text-atalaya-cyan hover:border-atalaya-cyan',
    peligro: 'text-atalaya-crimson hover:border-atalaya-crimson',
    advertencia: 'text-atalaya-amber hover:border-atalaya-amber',
};

/**
 * Botón de acción de fila: cuadrado, con ícono y la acción escrita en el
 * tooltip y para lectores de pantalla. Con `href` es un enlace (`descarga`
 * para archivos); si no, un botón.
 */
interface AccionProps {
    icono: Icono;
    etiqueta: string;
    href?: string;
    descarga?: boolean;
    onClick?: MouseEventHandler<HTMLButtonElement>;
    variante?: Variante;
    disabled?: boolean;
}

export function Accion({ icono, etiqueta, href, descarga = false, onClick, variante = 'normal', disabled = false }: AccionProps) {
    const clases = `inline-flex items-center justify-center border border-atalaya-border p-1.5 transition-colors hover:bg-atalaya-elevated hover:text-white disabled:cursor-not-allowed disabled:opacity-40 ${VARIANTES[variante]}`;
    const svg = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d={ICONOS[icono]} />
        </svg>
    );

    if (href && descarga) {
        return (
            <a href={href} title={etiqueta} aria-label={etiqueta} className={clases}>
                {svg}
            </a>
        );
    }

    if (href) {
        return (
            <Link href={href} title={etiqueta} aria-label={etiqueta} className={clases}>
                {svg}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} disabled={disabled} title={etiqueta} aria-label={etiqueta} className={clases}>
            {svg}
        </button>
    );
}

/**
 * Etiqueta compacta (estado, rol, categoría). `color`: clases de borde/fondo/texto;
 * `style` para colores fuera de la paleta Tailwind (grupos de evento).
 */
export function Insignia({
    color = 'border-atalaya-border bg-atalaya-canvas text-atalaya-text-muted',
    style,
    children,
    title,
}: Hijos & { color?: string; style?: CSSProperties; title?: string }) {
    return (
        <span title={title} style={style} className={`inline-flex border px-2 py-0.5 font-mono text-[10px] font-bold uppercase leading-4 ${color}`}>
            {children}
        </span>
    );
}

/** Paginación de Laravel (links del paginador). */
export function Paginacion({ paginador }: { paginador?: Paginador | null }) {
    if (!paginador || paginador.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-1" aria-label="Paginación">
            {paginador.links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className={
                            'border px-3 py-1 font-mono text-xs ' +
                            (link.active
                                ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                : 'border-atalaya-border bg-atalaya-surface text-atalaya-text-muted hover:bg-atalaya-canvas')
                        }
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={i}
                        className="border border-atalaya-border px-3 py-1 font-mono text-xs text-atalaya-text-dim"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
            <span className="ml-2 font-mono text-xs text-atalaya-text-dim">
                {paginador.from}–{paginador.to} de {paginador.total}
            </span>
        </nav>
    );
}
