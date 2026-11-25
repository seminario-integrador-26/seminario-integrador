import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Pestañas de los tableros; cada una solo si el usuario tiene el permiso. */
const PESTANAS = [
    { ruta: 'tableros.operativo', etiqueta: 'Control operativo', permiso: 'eventos.consultar' },
    { ruta: 'tableros.estrategico', etiqueta: 'Análisis estratégico', permiso: 'dashboards.ver' },
    { ruta: 'tableros.calor', etiqueta: 'Mapa de calor', permiso: 'dashboards.ver' },
] as const;

export function PestanasTableros({ extra }: { extra?: ReactNode }) {
    const permisos = usePage<PageProps>().props.auth.permissions ?? [];

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <nav aria-label="Tableros" className="flex flex-wrap gap-1">
                {PESTANAS.filter((p) => permisos.includes(p.permiso)).map((p) => {
                    const activa = route().current(p.ruta);

                    return (
                        <Link
                            key={p.ruta}
                            href={route(p.ruta)}
                            aria-current={activa ? 'page' : undefined}
                            className={
                                'border px-3 py-1.5 font-mono text-[11px] font-bold uppercase tracking-wider transition ' +
                                (activa
                                    ? 'border-atalaya-cyan bg-atalaya-cyan/20 text-atalaya-cyan'
                                    : 'border-atalaya-border text-atalaya-text-muted hover:text-white')
                            }
                        >
                            {p.etiqueta}
                        </Link>
                    );
                })}
            </nav>
            {extra}
        </div>
    );
}

/** Tarjeta con título chico en mayúsculas, como los paneles de Análisis. */
export function Tarjeta({
    titulo,
    nota,
    acciones,
    children,
    className = '',
}: {
    titulo: ReactNode;
    nota?: ReactNode;
    acciones?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`border border-atalaya-border bg-atalaya-surface p-5 ${className}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-mono text-xs font-bold uppercase tracking-widest text-atalaya-text-dim">{titulo}</h3>
                    {nota && <p className="mt-1 text-xs text-atalaya-text-dim">{nota}</p>}
                </div>
                {acciones}
            </div>
            <div className="mt-4">{children}</div>
        </section>
    );
}

/**
 * Variación %: más novedades = flecha roja hacia arriba; menos = verde hacia
 * abajo. La flecha y el signo acompañan al color (no solo el color).
 */
export function Delta({ valor, sinDatos = 'sin datos para comparar' }: { valor: number | null | undefined; sinDatos?: string }) {
    if (valor === null || valor === undefined) {
        return <span className="text-atalaya-text-dim">{sinDatos}</span>;
    }

    const sube = valor > 0;
    const baja = valor < 0;

    return (
        <span className={`font-mono font-bold ${sube ? 'text-atalaya-crimson' : baja ? 'text-emerald-400' : 'text-atalaya-text-muted'}`}>
            {sube ? '▲ +' : baja ? '▼ −' : '= '}
            {Math.abs(valor)}%
        </span>
    );
}

export const TINTA = { principal: '#E2E8F0', secundaria: '#94A3B8', grilla: '#1E3350' };

export const ejeProps = {
    stroke: TINTA.grilla,
    tick: { fill: TINTA.secundaria, fontSize: 11 },
    tickLine: false,
};
