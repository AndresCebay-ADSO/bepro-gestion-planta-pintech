import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function FlashMessages() {
    const { flash } = usePage().props as any;
    const [dismissedMessage, setDismissedMessage] = useState<string | null>(null);

    // Derivamos el mensaje directamente del prop (Single Source of Truth)
    const currentFlash = flash.message || flash.error || null;
    const message = flash.message
        ? { type: 'success' as const, text: flash.message }
        : flash.error
          ? { type: 'error' as const, text: flash.error }
          : null;

    // Si el mensaje actual es el mismo que el usuario ya cerró, no mostramos nada
    if (!message || dismissedMessage === currentFlash) return null;

    return (
        <div className="mb-4">
            <Alert variant={message.type === 'error' ? 'destructive' : 'default'} className="relative">
                {message.type === 'error' ? (
                    <AlertCircle className="h-4 w-4" />
                ) : (
                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                )}
                <AlertTitle>{message.type === 'error' ? 'Error' : 'Éxito'}</AlertTitle>
                <AlertDescription>{message.text}</AlertDescription>
                <button
                    onClick={() => setDismissedMessage(currentFlash)}
                    className="absolute right-2 top-2 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100"
                >
                    <X className="h-4 w-4" />
                </button>
            </Alert>
        </div>
    );
}
