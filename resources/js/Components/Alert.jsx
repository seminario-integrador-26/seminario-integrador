/**
 * Alerta con el tema táctico "Atalaya". Reemplaza los bloques sueltos de
 * bg-green-50 / bg-red-50 para que combinen con la consola oscura.
 * variant: 'success' | 'error' | 'info'.
 */
const VARIANTES = {
    success: {
        contenedor: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
        acento: 'bg-emerald-400',
        etiqueta: 'OK',
        etiquetaColor: 'text-emerald-400',
    },
    error: {
        contenedor: 'border-atalaya-crimson/50 bg-atalaya-crimson/10 text-red-100',
        acento: 'bg-atalaya-crimson',
        etiqueta: 'Error',
        etiquetaColor: 'text-atalaya-crimson',
    },
    info: {
        contenedor: 'border-atalaya-cyan/40 bg-atalaya-cyan/10 text-atalaya-cyan',
        acento: 'bg-atalaya-cyan',
        etiqueta: 'Info',
        etiquetaColor: 'text-atalaya-cyan',
    },
};

export default function Alert({ variant = 'info', children, className = '' }) {
    const v = VARIANTES[variant] ?? VARIANTES.info;

    return (
        <div
            role="alert"
            className={`flex items-stretch overflow-hidden border ${v.contenedor} ${className}`}
        >
            <div className={`w-1 shrink-0 ${v.acento}`} aria-hidden="true" />
            <div className="flex flex-1 items-center gap-3 px-4 py-3">
                <span
                    className={`shrink-0 font-mono text-[10px] font-bold uppercase tracking-widest ${v.etiquetaColor}`}
                >
                    [ {v.etiqueta} ]
                </span>
                <span className="font-mono text-xs leading-relaxed">
                    {children}
                </span>
            </div>
        </div>
    );
}
