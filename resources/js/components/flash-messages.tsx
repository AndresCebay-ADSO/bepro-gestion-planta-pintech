import { router, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export function FlashMessages() {
    const { flash } = usePage();
    const [dismissed, setDismissed] = useState<{
        success?: boolean;
        error?: boolean;
    }>({});

    // El evento `flash` se dispara en cada respuesta que trae mensajes flash, incluso si el texto es idéntico al
    // anterior en la misma página: eso limpia el cierre sin depender de referencias en `page.props`.
    useEffect(() => router.on('flash', () => setDismissed({})), []);

    const showSuccess = Boolean(flash.success) && !dismissed.success;
    const showError = Boolean(flash.error) && !dismissed.error;

    if (!showSuccess && !showError) {
        return null;
    }

    const dismiss = (key: 'success' | 'error') => {
        setDismissed((prev) => ({
            ...prev,
            [key]: true,
        }));
    };

    const messages = [
        ...(showSuccess && flash.success
            ? [
                  {
                      key: 'success' as const,
                      variant: 'success' as const,
                      title: 'Éxito',
                      text: flash.success,
                      Icon: CheckCircle2,
                  },
              ]
            : []),
        ...(showError && flash.error
            ? [
                  {
                      key: 'error' as const,
                      variant: 'destructive' as const,
                      title: 'Error',
                      text: flash.error,
                      Icon: AlertCircle,
                  },
              ]
            : []),
    ];

    return (
        <div className="mb-4 space-y-2">
            {messages.map(({ key, variant, title, text, Icon }) => (
                // El botón de cerrar va fuera del `role="alert"`: dentro, el lector de pantalla leería «Cerrar aviso»
                // junto con el mensaje.
                <div key={key} className="relative">
                    <Alert variant={variant} className="pr-10">
                        <Icon className="h-4 w-4" />
                        <AlertTitle>{title}</AlertTitle>
                        <AlertDescription>{text}</AlertDescription>
                    </Alert>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={() => dismiss(key)}
                                className="absolute top-2 right-2 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100"
                            >
                                <X className="h-4 w-4" />
                                <span className="sr-only">Cerrar aviso</span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>Cerrar aviso</TooltipContent>
                    </Tooltip>
                </div>
            ))}
        </div>
    );
}
