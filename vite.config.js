import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    // Host fijo = mismo origen que la app (localhost:8000). Evita que el `hot`
    // apunte a [::1] (IPv6) y que el navegador no cargue el bundle. strictPort:
    // si 5173 está ocupado, falla en vez de saltar a 5174 (delata duplicados).
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
    },
});
