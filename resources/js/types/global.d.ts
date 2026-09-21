import type { Auth } from '@/types/auth';
import type { NewAlert, RecentAlert, WarehouseContext } from '@/types/shared';

/** Props que HandleInertiaRequests::share() envía a todas las páginas. */
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** `null` sin sesión. */
            warehouseContext: WarehouseContext | null;
            unresolvedAlertsCount: number;
            recentAlerts: RecentAlert[];
            flash: {
                success?: string | null;
                error?: string | null;
                new_alerts: NewAlert[];
            };
            [key: string]: unknown;
        };
    }
}
