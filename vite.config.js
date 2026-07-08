import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/@univerjs')) {
                        return 'univer';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input:  [
        'resources/css/app.css',
        'resources/js/app.js',
    ],
            
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
