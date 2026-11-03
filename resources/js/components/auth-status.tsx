import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';

/** La respuesta a una acción en las pantallas de acceso (por ejemplo, se envió el enlace). */
export function AuthStatus({ children }: { children: ReactNode }) {
    return (
        <Alert variant="success" role="alert" className="mb-4">
            <AlertDescription>{children}</AlertDescription>
        </Alert>
    );
}
