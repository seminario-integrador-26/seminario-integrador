/**
 * Marca del Centro de Monitoreo: lente de cámara sobre azul marino.
 * `invertido` para usar sobre fondos de marca (bg-brand-deep).
 */
export default function ApplicationLogo({
    invertido = false,
    className = '',
    ...props
}) {
    const fondo = invertido ? 'fill-brand-deep-ink' : 'fill-brand';
    const trazo = invertido ? 'stroke-brand-deep' : 'stroke-brand-ink';
    const centro = invertido ? 'fill-brand-deep' : 'fill-brand-ink';

    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={className}
        >
            <rect width="32" height="32" rx="8" className={fondo} />
            <circle
                cx="16"
                cy="16"
                r="8.5"
                fill="none"
                strokeWidth="2.5"
                className={trazo}
            />
            <circle cx="16" cy="16" r="3" className={centro} />
        </svg>
    );
}
