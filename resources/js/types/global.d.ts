import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash?: {
                message?: string;
                error?: string;
                [key: string]: unknown;
            };
            [key: string]: unknown;
        };
    }
}
