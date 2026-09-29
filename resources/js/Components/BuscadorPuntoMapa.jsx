import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { useMemo, useState } from 'react';

const MAX_RESULTADOS = 30;

const normalizar = (texto) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * Buscador compacto de Puntos de Monitoreo para el mapa del panel.
 * Al elegir uno llama a onSeleccionar(pm) para que el mapa lo enfoque.
 */
export default function BuscadorPuntoMapa({ puntos, onSeleccionar }) {
    const [query, setQuery] = useState('');

    const filtrados = useMemo(() => {
        const q = normalizar(query.trim());

        return q
            ? puntos.filter((p) =>
                  normalizar(`${p.codigo} ${p.nombre}`).includes(q),
              )
            : puntos;
    }, [puntos, query]);

    return (
        <Combobox
            value={null}
            onChange={(p) => p && onSeleccionar(p)}
            onClose={() => setQuery('')}
        >
            <ComboboxInput
                autoComplete="off"
                aria-label="Buscar punto de monitoreo"
                placeholder="Buscar PM por código o nombre…"
                className="block w-full rounded-none border-atalaya-border bg-atalaya-surface/95 py-1.5 font-mono text-xs text-white placeholder-atalaya-text-dim backdrop-blur-sm focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan"
                displayValue={() => query}
                onChange={(e) => setQuery(e.target.value)}
            />
            <ComboboxOptions
                anchor="bottom start"
                className="z-[1100] mt-1 max-h-72 w-[var(--input-width)] overflow-auto border border-atalaya-border bg-atalaya-surface py-1 font-mono text-xs text-atalaya-text-muted shadow-2xl empty:invisible"
            >
                {filtrados.length === 0 && (
                    <div className="px-3 py-2 text-atalaya-text-dim">
                        Sin resultados.
                    </div>
                )}
                {filtrados.slice(0, MAX_RESULTADOS).map((p) => (
                    <ComboboxOption
                        key={p.id}
                        value={p}
                        className="flex cursor-pointer items-center justify-between px-3 py-1.5 data-[focus]:bg-atalaya-elevated data-[focus]:text-atalaya-cyan"
                    >
                        <span className="truncate">
                            <span className="font-medium">{p.codigo}</span> —{' '}
                            {p.nombre}
                        </span>
                        <span className="ms-3 shrink-0 text-[10px] uppercase opacity-70">
                            {p.jurisdiccion}
                        </span>
                    </ComboboxOption>
                ))}
                {filtrados.length > MAX_RESULTADOS && (
                    <div className="px-3 py-2 text-[10px] text-atalaya-text-dim">
                        Mostrando {MAX_RESULTADOS} de {filtrados.length}.
                        Refiná la búsqueda.
                    </div>
                )}
            </ComboboxOptions>
        </Combobox>
    );
}
