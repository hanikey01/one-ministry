import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        assetsDir: 'static',
        rollupOptions: {
            external: ['@zoom/videosdk'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        origin: process.env.VITE_DEV_ORIGIN || 'http://localhost:5173',
      // Caddy owns the single CORS header for the proxied dev assets.
      cors: false,
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
            clientPort: Number(process.env.VITE_HMR_CLIENT_PORT || 5173),
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            usePolling: true,
            interval: 300,
        },
    },
});
