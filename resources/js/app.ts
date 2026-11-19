import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Pixelforge Studio';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const allPages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');
        const pagePath = `./Pages/${name}.vue`;

        if (pagePath.includes('/Components/') || !allPages[pagePath]) {
            throw new Error(`Inertia page not found: ${pagePath}`);
        }

        const page = await allPages[pagePath]();
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#fafafa',
    },
});
