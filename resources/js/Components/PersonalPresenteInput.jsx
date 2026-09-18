import { useState } from 'react';

/**
 * Input de "chips" para la lista de personal presente en la guardia (US-025).
 * Se agrega un nombre con Enter o coma; se quita con la × o con Backspace en
 * el campo vacío. `value` es un array de strings; `onChange` recibe el array.
 */
export default function PersonalPresenteInput({
    value = [],
    onChange,
    id = 'personal_presente',
    disabled = false,
}) {
    const [texto, setTexto] = useState('');

    const agregar = (nombre) => {
        const limpio = nombre.trim();

        if (limpio === '') {
            return;
        }

        // Evita duplicados exactos (case-insensitive).
        const yaEsta = value.some(
            (n) => n.toLowerCase() === limpio.toLowerCase(),
        );

        if (!yaEsta) {
            onChange([...value, limpio]);
        }

        setTexto('');
    };

    const quitar = (indice) => onChange(value.filter((_, i) => i !== indice));

    const onKeyDown = (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            agregar(texto);
        } else if (e.key === 'Backspace' && texto === '' && value.length > 0) {
            quitar(value.length - 1);
        }
    };

    return (
        <div
            className={`mt-1 flex flex-wrap items-center gap-2 border border-atalaya-border bg-atalaya-canvas p-2 font-mono text-xs focus-within:border-atalaya-cyan ${
                disabled ? 'opacity-60' : ''
            }`}
        >
            {value.map((nombre, indice) => (
                <span
                    key={`${nombre}-${indice}`}
                    className="inline-flex items-center gap-1.5 border border-atalaya-cyan bg-atalaya-cyan/10 px-2 py-0.5 text-atalaya-cyan"
                >
                    {nombre}
                    {!disabled && (
                        <button
                            type="button"
                            onClick={() => quitar(indice)}
                            className="text-atalaya-cyan hover:text-white"
                            aria-label={`Quitar ${nombre}`}
                        >
                            ×
                        </button>
                    )}
                </span>
            ))}

            <input
                id={id}
                type="text"
                disabled={disabled}
                value={texto}
                onChange={(e) => setTexto(e.target.value)}
                onKeyDown={onKeyDown}
                onBlur={() => agregar(texto)}
                placeholder={value.length === 0 ? 'Nombre y Enter…' : ''}
                className="min-w-[140px] flex-1 border-0 bg-transparent p-0 text-white placeholder:text-atalaya-text-dim focus:ring-0"
            />
        </div>
    );
}
