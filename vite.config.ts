import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    optimizeDeps: {
        include: ['chart.js/auto', 'pptxviewjs'],
    },
    build: {
        chunkSizeWarningLimit: 900,
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    // Rename .mjs to .js to fix MIME type issues with pdf.js worker
                    if (/\.mjs$/.test(assetInfo.names?.[0] ?? '')) {
                        return 'assets/[name]-[hash].js';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                manualChunks: {
                    'viewer-pdf': ['react-pdf', 'pdfjs-dist'],
                    'viewer-xlsx': ['xlsx'],
                    'viewer-docx': ['docx-preview'],
                    'viewer-pptx': ['pptxviewjs', 'chart.js'],
                },
            },
        },
    },
});
