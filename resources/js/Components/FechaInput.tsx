import { useEffect, useRef, useState, type FocusEvent } from 'react';

interface Props {
    id?: string;
    /** Fecha en formato YYYY-MM-DD (o vacío), igual que un input type="date". */
    value: string;
    /** Emite YYYY-MM-DD cuando la fecha está completa y es válida, o '' al vaciarla. */
    onChange?: (e: { target: { value: string } }) => void;
    onBlur?: (e: FocusEvent<HTMLInputElement>) => void;
    min?: string;
    max?: string;
    readOnly?: boolean;
    disabled?: boolean;
    className?: string;
}

const isoADmy = (iso: string) => {
    const [a, m, d] = iso.split('-');
    return a && m && d ? `${d}/${m}/${a}` : '';
};

/** "dd/mm/aaaa" → "aaaa-mm-dd", o null si la fecha no existe. */
const dmyAIso = (texto: string) => {
    const [d, m, a] = texto.split('/').map(Number);
    const fecha = new Date(a, m - 1, d);
    if (fecha.getFullYear() !== a || fecha.getMonth() !== m - 1 || fecha.getDate() !== d) {
        return null;
    }
    return `${a}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
};

/** Agrega las barras a medida que se escribe: 2810 → 28/10. */
const enmascarar = (texto: string) => {
    const digitos = texto.replace(/\D/g, '').slice(0, 8);
    return [digitos.slice(0, 2), digitos.slice(2, 4), digitos.slice(4)]
        .filter(Boolean)
        .join('/');
};

/**
 * Campo de fecha en formato argentino (dd/mm/aaaa), sin depender del idioma
 * del navegador como el input type="date". El calendario sigue disponible
 * con el botón.
 */
export default function FechaInput({
    id,
    value,
    onChange,
    onBlur,
    min,
    max,
    readOnly = false,
    disabled = false,
    className = '',
}: Props) {
    const [texto, setTexto] = useState(isoADmy(value));
    const calendario = useRef<HTMLInputElement>(null);

    useEffect(() => setTexto(isoADmy(value)), [value]);

    const emitir = (iso: string) => onChange?.({ target: { value: iso } });

    const escribir = (nuevo: string) => {
        const enmascarado = enmascarar(nuevo);
        setTexto(enmascarado);
        if (enmascarado === '') {
            emitir('');
        } else if (enmascarado.length === 10) {
            const iso = dmyAIso(enmascarado);
            if (iso) {
                emitir(iso);
            }
        }
    };

    const bloqueado = readOnly || disabled;

    return (
        <div className={'relative ' + className}>
            <input
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                placeholder="dd/mm/aaaa"
                maxLength={10}
                readOnly={readOnly}
                disabled={disabled}
                value={texto}
                onChange={(e) => escribir(e.target.value)}
                onBlur={(e) => {
                    // Lo incompleto o inválido vuelve al último valor bueno.
                    setTexto(isoADmy(value));
                    onBlur?.(e);
                }}
                className="block w-full rounded-none border-atalaya-border bg-atalaya-canvas pe-8 font-mono text-xs text-white placeholder-atalaya-text-dim focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan disabled:cursor-not-allowed disabled:text-atalaya-text-muted"
            />
            {!bloqueado && (
                <>
                    <input
                        ref={calendario}
                        type="date"
                        tabIndex={-1}
                        aria-hidden="true"
                        min={min}
                        max={max}
                        value={value}
                        onChange={(e) => emitir(e.target.value)}
                        className="pointer-events-none absolute bottom-0 end-0 h-0 w-0 opacity-0"
                    />
                    <button
                        type="button"
                        aria-label="Abrir calendario"
                        onClick={() => calendario.current?.showPicker()}
                        className="absolute end-2 top-1/2 -translate-y-1/2 text-atalaya-text-muted hover:text-atalaya-cyan"
                    >
                        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path
                                fillRule="evenodd"
                                d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </button>
                </>
            )}
        </div>
    );
}
