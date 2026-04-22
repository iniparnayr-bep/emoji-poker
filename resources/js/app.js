import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import 'primeicons/primeicons.css';

const appName = import.meta.env.VITE_APP_NAME || 'Emoji Poker';

createInertiaApp({
    title: (title) => title ? `${title} — Emoji Poker` : 'Emoji Poker',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, { unstyled: true }) // we'll style everything ourselves
            .use(ToastService)
            .mount(el);
    },
    progress: { color: '#d4ff3a' },
});
