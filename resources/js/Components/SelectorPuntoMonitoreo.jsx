import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { useMemo, useState } from 'react';

const MAX_RESULTADOS = 50;

const normalizar = (texto) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * Buscador de Puntos de Monitoreo por código o nombre, con filtro por jurisdicción.
 * value/onChange trabajan con el id del PM (o null).
 */
export default function SelectorPuntoMonitoreo({
    id,
    puntos,
    jurisdicciones,
    value,
    onChange,
}) {
    const [query, setQuery] = useState('');
    const [jurisdiccion, setJurisdiccion] = useState('');

    const seleccionado = puntos.find((p) => p.id === value) ?? null;

    const conteo = useMemo(() => {
        const acc = {};
        for (const p of puntos) {
            acc[p.jurisdiccion] = (acc[p.jurisdiccion] ?? 0) + 1;
        }
        return acc;
    }, [puntos]);

    const filtrados = useMemo(() => {
        const q = normalizar(query.trim());

        return puntos.filter(
            (p) =>
                (!jurisdiccion || p.jurisdiccion === jurisdiccion) &&
                (!q || normalizar(`${p.codigo} ${p.nombre}`).includes(q)),
        );
    }, [puntos, query, jurisdiccion]);

    const filtros = [
        ['', `Todos (${puntos.length})`],
        ...Object.entries(jurisdicciones).map(([clave, etiqueta]) => [
            clave,
            `${etiqueta}es (${conteo[clave] ?? 0})`,
        ]),
    ];

    return (
        <div className="mt-1 space-y-2">
            <div className="flex flex-wrap gap-1.5">
                {filtros.map(([clave, etiqueta]) => (
                    <button
                        key={clave || 'todos'}
                        type="button"
                        aria-pressed={jurisdiccion === clave}
                        onClick={() => setJurisdiccion(clave)}
                        className={
                            'rounded-md px-2.5 py-1 text-xs font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ' +
                            (jurisdiccion === clave
                                ? 'bg-brand text-brand-ink'
                                : 'bg-surface-2 text-ink-muted hover:text-ink')
                        }
                    >
                        {etiqueta}
                    </button>
                ))}
            </div>

            <div className="flex gap-2">
                <Combobox
                    value={seleccionado}
                    onChange={(p) => onChange(p?.id ?? null)}
                    onClose={() => setQuery('')}
                >
                    <ComboboxInput
                        id={id}
                        autoComplete="off"
                        placeholder="Buscar por código o nombre…"
                        className="campo"
                        displayValue={(p) =>
                            p ? `${p.codigo} · ${p.nombre}` : ''
                        }
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <ComboboxOptions
                        anchor="bottom start"
                        className="z-[1100] mt-1 max-h-72 w-[var(--input-width)] overflow-auto rounded-lg border border-line bg-surface py-1 text-sm shadow-panel empty:invisible"
                    >
                        {filtrados.length === 0 && (
                            <div className="px-3 py-2 text-ink-muted">
                                Sin resultados.
                            </div>
                        )}
                        {filtrados.slice(0, MAX_RESULTADOS).map((p) => (
                            <ComboboxOption
                                key={p.id}
                                value={p}
                                className="flex cursor-pointer items-center justify-between px-3 py-2 text-ink data-[focus]:bg-accent-soft data-[selected]:font-medium"
                            >
                                <span>
                                    <span className="font-mono text-xs font-medium">
                                        {p.codigo}
                                    </span>{' '}
                                    {p.nombre}
                                </span>
                                <span className="ms-3 text-xs text-ink-subtle">
                                    {jurisdicciones[p.jurisdiccion]}
                                </span>
                            </ComboboxOption>
                        ))}
                        {filtrados.length > MAX_RESULTADOS && (
                            <div className="px-3 py-2 text-xs text-ink-muted">
                                Mostrando {MAX_RESULTADOS} de{' '}
                                {filtrados.length}. Refiná la búsqueda.
                            </div>
                        )}
                    </ComboboxOptions>
                </Combobox>

                {seleccionado && (
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        className="shrink-0 rounded-md border border-line-strong bg-surface px-3 text-sm font-medium text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >
                        Quitar
                    </button>
                )}
            </div>
        </div>
    );
}
