import { usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * El aviso de error de las pantallas de acceso (por ejemplo, demasiados intentos), que no tienen el layout de la app
 * ni su `<FlashMessages>`. Lo usan el login, «¿Olvidaste tu contraseña?» y `AuthLayout`.
 */
export function AuthFlashError({ className }: { className?: string }) {
    const { flash } = usePage();

    if (!flash.error) {
        return null;
    }

    return (
        <Alert variant="destructive" className={className}>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{flash.error}</AlertDescription>
        </Alert>
    );
}
