import { elegirTema, leerPreferencia, suscribir } from '@/tema';
import { useSyncExternalStore } from 'react';

const OPCIONES = [
    ['claro', 'Claro'],
    ['marino', 'Marino'],
    ['sistema', 'Auto'],
];

/**
 * Control segmentado de tema. Todas las instancias comparten estado vía
 * resources/js/tema.js (menú de escritorio y móvil quedan sincronizados).
 */
export default function SelectorTema({ className = '' }) {
    const preferencia = useSyncExternalStore(suscribir, leerPreferencia);

    return (
        <div
            role="radiogroup"
            aria-label="Tema de la interfaz"
            className={
                'inline-flex rounded-md border border-line bg-surface-2 p-0.5 ' +
                className
            }
        >
            {OPCIONES.map(([valor, etiqueta]) => {
                const activo = preferencia === valor;

                return (
                    <button
                        key={valor}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        onClick={() => elegirTema(valor)}
                        className={
                            'rounded px-2.5 py-1 text-xs font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ' +
                            (activo
                                ? 'bg-surface text-ink shadow-sm'
                                : 'text-ink-muted hover:text-ink')
                        }
                    >
                        {etiqueta}
                    </button>
                );
            })}
        </div>
    );
}
