import '../css/app.css';
import './bootstrap';
import { initTheme } from './composables/useTheme.js';
import { flushPendingMessages } from './lib/flushPendingMessages.js';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const userPref = props.initialPage?.props?.auth?.user?.theme_preference;
        initTheme(userPref);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

const isProd = Boolean(import.meta.env.PROD);

if (isProd && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

window.addEventListener('online', () => {
    flushPendingMessages().catch(() => {});
});
