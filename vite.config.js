import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const devPort = Number(process.env.VITE_DEV_PORT ?? 5174);
const appOrigin = process.env.VITE_APP_ORIGIN ?? 'http://localhost:8080';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/public.ts', 'resources/js/admin.ts'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: devPort,
        strictPort: true,
        origin: `http://localhost:${devPort}`,
        cors: {
            origin: appOrigin,
        },
        hmr: {
            host: 'localhost',
            clientPort: devPort,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        sourcemap: false,
    },
});
