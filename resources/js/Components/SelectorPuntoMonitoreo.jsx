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
            <div className="flex flex-wrap gap-2">
                {filtros.map(([clave, etiqueta]) => (
                    <button
                        key={clave || 'todos'}
                        type="button"
                        onClick={() => setJurisdiccion(clave)}
                        className={
                            'rounded-full px-3 py-1 text-xs font-medium ' +
                            (jurisdiccion === clave
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200')
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
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        displayValue={(p) =>
                            p ? `${p.codigo} — ${p.nombre}` : ''
                        }
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <ComboboxOptions
                        anchor="bottom start"
                        className="z-[1100] mt-1 max-h-72 w-[var(--input-width)] overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 empty:invisible"
                    >
                        {filtrados.length === 0 && (
                            <div className="px-3 py-2 text-gray-500">
                                Sin resultados.
                            </div>
                        )}
                        {filtrados.slice(0, MAX_RESULTADOS).map((p) => (
                            <ComboboxOption
                                key={p.id}
                                value={p}
                                className="flex cursor-pointer items-center justify-between px-3 py-2 data-[focus]:bg-indigo-600 data-[focus]:text-white"
                            >
                                <span>
                                    <span className="font-medium">
                                        {p.codigo}
                                    </span>{' '}
                                    — {p.nombre}
                                </span>
                                <span className="ms-3 text-xs opacity-70">
                                    {jurisdicciones[p.jurisdiccion]}
                                </span>
                            </ComboboxOption>
                        ))}
                        {filtrados.length > MAX_RESULTADOS && (
                            <div className="px-3 py-2 text-xs text-gray-500">
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
                        className="shrink-0 rounded-md border border-gray-300 px-3 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        Quitar
                    </button>
                )}
            </div>
        </div>
    );
}
