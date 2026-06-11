import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            unresolvedAlertsCount?: number;
            recentAlerts?: Array<{
                id: number;
                type: string;
                type_label: string;
                severity: string;
                severity_label: string;
                message: string;
                created_at: string | null;
                raw_material_code: string | null;
            }>;
            flash?: {
                message?: string;
                success?: string;
                error?: string;
                new_alerts?: Array<{
                    id: number;
                    message: string;
                    severity: string;
                    type: string;
                    type_label: string;
                }>;
                [key: string]: unknown;
            };
            [key: string]: unknown;
        };
    }
}
