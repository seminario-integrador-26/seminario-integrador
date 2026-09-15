/**
 * Preferencia de tema de la interfaz: "claro", "marino" (azul marino oscuro)
 * o "sistema" (sigue prefers-color-scheme). Se guarda en localStorage y se
 * aplica como <html data-theme>. El script inline de app.blade.php repite esta
 * lógica antes del primer pintado: mantener ambos en sincronía.
 */
const CLAVE = 'tema';
const TEMAS = ['claro', 'marino'];
const oyentes = new Set();

// Respaldo cuando localStorage no está disponible (p. ej. modo privado).
let enMemoria = null;

const prefiereOscuro = () => window.matchMedia('(prefers-color-scheme: dark)');

export function leerPreferencia() {
    try {
        const valor = localStorage.getItem(CLAVE);
        return TEMAS.includes(valor) ? valor : 'sistema';
    } catch {
        return enMemoria ?? 'sistema';
    }
}

function aplicar(preferencia) {
    const tema =
        preferencia === 'sistema'
            ? prefiereOscuro().matches
                ? 'marino'
                : 'claro'
            : preferencia;

    document.documentElement.setAttribute('data-theme', tema);
}

export function elegirTema(preferencia) {
    enMemoria = preferencia;

    try {
        if (preferencia === 'sistema') {
            localStorage.removeItem(CLAVE);
        } else {
            localStorage.setItem(CLAVE, preferencia);
        }
    } catch {
        // Sin almacenamiento: el tema vale sólo para esta pestaña.
    }

    aplicar(preferencia);
    oyentes.forEach((notificar) => notificar());
}

export function suscribir(notificar) {
    oyentes.add(notificar);

    return () => oyentes.delete(notificar);
}

prefiereOscuro().addEventListener('change', () => {
    if (leerPreferencia() === 'sistema') {
        aplicar('sistema');
    }
});
