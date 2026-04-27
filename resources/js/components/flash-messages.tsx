import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function FlashMessages() {
    const { flash } = usePage().props as any;
    const [isVisible, setIsVisible] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    useEffect(() => {
        if (flash.message) {
            setMessage({ type: 'success', text: flash.message });
            setIsVisible(true);
        } else if (flash.error) {
            setMessage({ type: 'error', text: flash.error });
            setIsVisible(true);
        }
    }, [flash]);

    if (!isVisible || !message) return null;

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
                    onClick={() => setIsVisible(false)}
                    className="absolute right-2 top-2 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100"
                >
                    <X className="h-4 w-4" />
                </button>
            </Alert>
        </div>
    );
}
