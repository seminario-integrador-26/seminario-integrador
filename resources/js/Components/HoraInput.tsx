import TextInput from '@/Components/TextInput';
import { useEffect, useRef, useState, type FocusEvent } from 'react';

interface Props {
    id?: string;
    /** Hora en formato HH:MM o HH:MM:SS (o vacío), igual que un input type="time". */
    value: string;
    /** Emite la hora cuando está completa y es válida, o '' al vaciarla. */
    onChange?: (e: { target: { value: string } }) => void;
    onBlur?: (e: FocusEvent<HTMLInputElement>) => void;
    /** Permite cargar segundos (HH:MM:SS); son opcionales al tipear. */
    segundos?: boolean;
    /** Si está vacío, el reloj carga la hora actual al abrirse. */
    ahoraAlAbrir?: boolean;
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

const dosDigitos = (n: number) => String(n).padStart(2, '0');

const HORAS = Array.from({ length: 24 }, (_, i) => dosDigitos(i));
const MINUTOS = Array.from({ length: 60 }, (_, i) => dosDigitos(i));

/**
 * Campo de hora en formato 24 hs, sin depender del idioma del navegador
 * (el input type="time" muestra AM/PM con el navegador en inglés). El reloj
 * abre un selector propio de horas y minutos (y segundos), también en 24 hs.
 */
export default function HoraInput({
    id,
    value,
    onChange,
    onBlur,
    segundos = false,
    ahoraAlAbrir = false,
    readOnly = false,
    disabled = false,
    className = '',
}: Props) {
    const [texto, setTexto] = useState(value);
    const [abierto, setAbierto] = useState(false);
    const contenedor = useRef<HTMLDivElement>(null);
    const selector = useRef<HTMLDivElement>(null);

    useEffect(() => setTexto(value), [value]);

    // Se cierra al hacer clic afuera o con Escape.
    useEffect(() => {
        if (!abierto) return;
        const afuera = (e: MouseEvent) => {
            if (!contenedor.current?.contains(e.target as Node)) setAbierto(false);
        };
        const escape = (e: KeyboardEvent) => e.key === 'Escape' && setAbierto(false);
        document.addEventListener('mousedown', afuera);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('mousedown', afuera);
            document.removeEventListener('keydown', escape);
        };
    }, [abierto]);

    // Al abrir, cada columna se desplaza hasta el valor elegido.
    useEffect(() => {
        if (!abierto) return;
        selector.current?.querySelectorAll<HTMLElement>('[data-columna]').forEach((columna) => {
            const elegido = columna.querySelector<HTMLElement>('[aria-selected="true"]');
            columna.scrollTop = elegido ? elegido.offsetTop - columna.clientHeight / 2 + elegido.clientHeight / 2 : 0;
        });
    }, [abierto]);

    const escribir = (nuevo: string) => {
        const enmascarado = enmascarar(nuevo, segundos ? 6 : 4);
        setTexto(enmascarado);
        // HH:MM ya es una hora completa; los segundos son opcionales.
        if (enmascarado === '' || ((enmascarado.length === 5 || enmascarado.length === 8) && esValida(enmascarado))) {
            onChange?.({ target: { value: enmascarado } });
        }
    };

    const [hh, mm, ss] = value ? value.split(':') : [];

    /** Cambia una parte (0 hora, 1 minutos, 2 segundos); lo que falta va en 00. */
    const elegir = (parte: number, valor: string) => {
        const partes = [hh ?? '00', mm ?? '00', ...(segundos ? [ss ?? '00'] : [])];
        partes[parte] = valor;
        onChange?.({ target: { value: partes.join(':') } });
        // Elegidos los minutos (o los segundos), la hora ya está completa.
        if (parte === partes.length - 1) setAbierto(false);
    };

    const bloqueado = readOnly || disabled;

    const columna = (etiqueta: string, opciones: string[], actual: string | undefined, parte: number) => (
        <div className="flex flex-col">
            <span className="border-b border-atalaya-border px-2 py-1 text-center font-mono text-[10px] uppercase tracking-widest text-atalaya-text-dim">
                {etiqueta}
            </span>
            <div data-columna role="listbox" aria-label={etiqueta} className="h-48 overflow-y-auto">
                {opciones.map((opcion) => (
                    <button
                        key={opcion}
                        type="button"
                        role="option"
                        aria-selected={opcion === actual}
                        onClick={() => elegir(parte, opcion)}
                        className={
                            'block w-12 px-2 py-1 text-center font-mono text-xs '
                            + (opcion === actual
                                ? 'bg-atalaya-cyan/20 text-atalaya-cyan'
                                : 'text-atalaya-text-muted hover:bg-atalaya-canvas hover:text-white')
                        }
                    >
                        {opcion}
                    </button>
                ))}
            </div>
        </div>
    );

    return (
        <div ref={contenedor} className={'relative ' + className}>
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
            <button
                type="button"
                aria-label="Elegir hora"
                aria-expanded={abierto}
                disabled={bloqueado}
                onClick={() => {
                    if (!abierto && ahoraAlAbrir && !value) {
                        onChange?.({ target: { value: new Date().toTimeString().slice(0, segundos ? 8 : 5) } });
                    }
                    setAbierto((a) => !a);
                }}
                className="absolute end-2 top-1/2 -translate-y-1/2 text-atalaya-text-muted hover:text-atalaya-cyan disabled:pointer-events-none"
            >
                <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fillRule="evenodd"
                        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>
            {abierto && (
                <div
                    ref={selector}
                    className="absolute end-0 top-full z-50 mt-1 flex divide-x divide-atalaya-border border border-atalaya-border bg-atalaya-surface shadow-lg"
                >
                    {columna('Hora', HORAS, hh, 0)}
                    {columna('Min', MINUTOS, mm, 1)}
                    {segundos && columna('Seg', MINUTOS, ss, 2)}
                </div>
            )}
        </div>
    );
}
