import type { Auth } from '@/types/auth';
import type { SharedEnums } from '@/types/enums';
import type { NewAlert, RecentAlert, WarehouseContext } from '@/types/shared';

/** Props que HandleInertiaRequests::share() envía a todas las páginas. */
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            success?: string;
            error?: string;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Hoy en la planta (`Y-m-d`): valor por defecto y límite de los campos de fecha. Lo valida PlantDate. */
            plantToday: string;
            /** `null` sin sesión. */
            warehouseContext: WarehouseContext | null;
            unresolvedAlertsCount: number;
            recentAlerts: RecentAlert[];
            /** Etiqueta y tono de los enums con color (`ToneEnums`). Llega una vez por sesión; sin sesión no existe. */
            enums?: SharedEnums;
            newAlerts: NewAlert[];
            [key: string]: unknown;
        };
    }
}
