import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Pruebas de funciones puras del front (sin navegador ni DOM). No reutiliza vite.config.ts: sus plugins generan las
// rutas de Wayfinder con artisan, que necesita la base de datos.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
