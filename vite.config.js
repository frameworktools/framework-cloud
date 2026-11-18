import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

// The vendored framework-embed files' content, in their URLs, so no cache serves an old copy under a new name
const embedHash = createHash('sha256')
    .update(readFileSync('public/vendor/framework-embed/framework-embed.css'))
    .update(readFileSync('public/vendor/framework-embed/framework-embed.iife.js'))
    .digest('hex')
    .slice(0, 12);

export default defineConfig({
    resolve: {
        alias: { '@': '/resources/js' },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/docs.css', 'resources/js/app.js', 'resources/js/artifact-reader.js', 'resources/js/static-artifact-reader.js', 'resources/js/landing-hero.js', 'resources/js/docs-search.js'],
            refresh: [`resources/views/**/*`],
        }),
        tailwindcss(),
        vue(),
    ],
    define: {
        __FRAMEWORK_EMBED_HASH__: JSON.stringify(embedHash),
    },
    server: {
        cors: true,
    },
});
