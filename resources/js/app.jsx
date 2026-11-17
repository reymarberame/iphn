import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

const appName = 'IPHN';

const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : `${appName} · Inter-Provincial Hospital Network`),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        // Lazy-load each Inertia page so the initial bundle stays small.
        return page().then((module) => module.default ?? module);
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0ba88e',
        showSpinner: false,
    },
});
