import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import ConfirmDialog from './Components/ConfirmDialog.vue';

createInertiaApp({
    title: (title) => (title ? `${title} — Framework Cloud` : 'Framework Cloud'),
    // Each page is its own chunk; app.blade.php preloads the first page's chunk
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('Head', Head)
            .component('Link', Link)
            .mount(el);

        // The shared confirmation dialog lives outside the page, so every layout has it
        const dialog = document.createElement('div');
        document.body.appendChild(dialog);
        createApp(ConfirmDialog).mount(dialog);
    },
    progress: { color: '#0ea5e9' },
});
