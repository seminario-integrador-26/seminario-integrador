import Alert from '@/Components/Alert';
import { usePage } from '@inertiajs/react';

/**
 * Muestra los mensajes flash (success / error) compartidos por
 * HandleInertiaRequests, con el estilo táctico de Alert. Colocar una vez cerca
 * del tope del contenido de la página.
 */
export default function FlashMessages({ className = '' }) {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className={`space-y-3 ${className}`}>
            {flash?.success && <Alert variant="success">{flash.success}</Alert>}
            {flash?.error && <Alert variant="error">{flash.error}</Alert>}
        </div>
    );
}
