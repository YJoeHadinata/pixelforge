/// <reference types="vite/client" />

import '@inertiajs/core';
import type { AuthUser } from '@/types/pixelforge';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: {
                user: AuthUser | null;
            };
            flash: {
                success?: string | null;
                error?: string | null;
            };
        };
    }
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
    export default component;
}
