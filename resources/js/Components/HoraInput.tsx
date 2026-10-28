import TextInput from '@/Components/TextInput';
import { useEffect, useState, type FocusEvent } from 'react';

interface Props {
    id?: string;
    /** Hora en formato HH:MM o HH:MM:SS (o vacío), igual que un input type="time". */
    value: string;
    /** Emite la hora cuando está completa y es válida, o '' al vaciarla. */
    onChange?: (e: { target: { value: string } }) => void;
    onBlur?: (e: FocusEvent<HTMLInputElement>) => void;
    /** Permite cargar segundos (HH:MM:SS); son opcionales al tipear. */
    segundos?: boolean;
    readOnly?: boolean;
    disabled?: boolean;
    className?: string;
}

/** Agrega los dos puntos a medida que se escribe: 1430 → 14:30. */
const enmascarar = (texto: string, maxDigitos: number) => {
    const digitos = texto.replace(/\D/g, '').slice(0, maxDigitos);
    return [digitos.slice(0, 2), digitos.slice(2, 4), digitos.slice(4)]
        .filter(Boolean)
        .join(':');
};

const esValida = (hora: string) =>
    hora.split(':').every((parte, i) => Number(parte) < (i === 0 ? 24 : 60));

/**
 * Campo de hora en formato 24 hs, sin depender del idioma del navegador
 * (el input type="time" muestra AM/PM con el navegador en inglés). El reloj
 * es solo indicativo: no abre el selector nativo, que traería el AM/PM.
 */
export default function HoraInput({
    id,
    value,
    onChange,
    onBlur,
    segundos = false,
    readOnly = false,
    disabled = false,
    className = '',
}: Props) {
    const [texto, setTexto] = useState(value);

    useEffect(() => setTexto(value), [value]);

    const escribir = (nuevo: string) => {
        const enmascarado = enmascarar(nuevo, segundos ? 6 : 4);
        setTexto(enmascarado);
        // HH:MM ya es una hora completa; los segundos son opcionales.
        if (enmascarado === '' || ((enmascarado.length === 5 || enmascarado.length === 8) && esValida(enmascarado))) {
            onChange?.({ target: { value: enmascarado } });
        }
    };

    return (
        <div className={'relative ' + className}>
            <TextInput
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                placeholder={segundos ? 'hh:mm:ss' : 'hh:mm'}
                maxLength={segundos ? 8 : 5}
                readOnly={readOnly}
                disabled={disabled}
                value={texto}
                onChange={(e) => escribir(e.target.value)}
                onBlur={(e) => {
                    // Lo incompleto o inválido vuelve al último valor bueno.
                    setTexto(value);
                    onBlur?.(e);
                }}
                className="block w-full pe-8"
            />
            <svg
                className="pointer-events-none absolute end-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-atalaya-text-muted"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"
                    clipRule="evenodd"
                />
            </svg>
        </div>
    );
}
