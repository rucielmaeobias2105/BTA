import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

/**
 * Static SPA build.
 *
 * Everything ships as hashed assets under `dist/`, which is the output
 * directory configured for the Vercel deployment. The build no longer knows
 * anything about Laravel: there is no server plugin, no `public/` target and
 * no PHP entry point.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        assetsDir: 'assets',
    },
    server: {
        port: 5173,
        strictPort: false,
    },
});
