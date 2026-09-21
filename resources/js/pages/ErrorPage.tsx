/**
 * Página de error (403, 404, 419, 500, 503)
 * Se renderiza desde el manejador de excepciones (bootstrap/app.php), sin layout:
 * un 404 de una ruta inexistente no pasa por la sesión y no hay usuario ni menú.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

type ErrorStatus = 403 | 404 | 419 | 500 | 503;

const MESSAGES: Record<ErrorStatus, { title: string; description: string }> = {
    403: {
        title: 'Acceso denegado',
        description:
            'Tu rol no tiene permiso para ver esta página o realizar esta acción. Si crees que lo necesitas, pide acceso a un administrador.',
    },
    404: {
        title: 'Página no encontrada',
        description:
            'La página que buscas no existe o el registro ya no está disponible.',
    },
    419: {
        title: 'Tu sesión expiró',
        description:
            'Pasó demasiado tiempo sin actividad y, por seguridad, la sesión se cerró. Vuelve a entrar y repite la acción; lo que no se guardó hay que ingresarlo de nuevo.',
    },
    500: {
        title: 'Error del servidor',
        description:
            'Ocurrió un error inesperado. Intenta de nuevo en unos minutos; si el problema continúa, avisa a soporte.',
    },
    503: {
        title: 'Sistema en mantenimiento',
        description:
            'Estamos realizando tareas de mantenimiento. Vuelve a intentarlo en unos minutos.',
    },
};

export default function ErrorPage({ status }: { status: ErrorStatus }) {
    const { auth } = usePage().props;
    const user = auth?.user ?? null;
    const message = MESSAGES[status] ?? MESSAGES[500];

    // Sin dashboard.view, "Ir al inicio" llevaría a otro 403.
    const homeHref = user
        ? user.permissions?.includes('dashboard.view')
            ? dashboard().url
            : null
        : login().url;

    // Si se llegó por un enlace directo no hay página anterior: "Volver" lleva al inicio (o al login).
    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(homeHref ?? login().url);
    };

    return (
        <>
            <Head title={`${status}: ${message.title}`} />

            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
                <div className="flex w-full max-w-md flex-col items-center gap-6 text-center">
                    <img
                        src="/images/logo-pintech.svg?v=1.1"
                        alt="Pintech logo"
                        className="h-20 w-auto object-contain"
                    />

                    <div className="space-y-2">
                        <p className="text-6xl font-semibold tracking-tight text-muted-foreground">
                            {status}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            {message.title}
                        </h1>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {message.description}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <Button variant="outline" onClick={goBack}>
                            <ArrowLeft />
                            Volver
                        </Button>

                        {homeHref && (
                            <Button asChild>
                                <Link href={homeHref}>
                                    <Home />
                                    {user ? 'Ir al inicio' : 'Iniciar sesión'}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
