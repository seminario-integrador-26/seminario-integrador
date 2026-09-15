const ESTILOS = {
    exito: 'border-success/30 bg-success-soft text-success',
    error: 'border-danger/30 bg-danger-soft text-danger',
    aviso: 'border-warning/30 bg-warning-soft text-warning',
    info: 'border-accent/30 bg-accent-soft text-accent',
};

/**
 * Mensaje contextual (flash, estado del turno, errores generales).
 */
export default function Alerta({ tipo = 'info', className = '', children }) {
    return (
        <div
            role={tipo === 'error' ? 'alert' : 'status'}
            className={`rounded-md border px-4 py-3 text-sm ${ESTILOS[tipo]} ${className}`}
        >
            {children}
        </div>
    );
}
