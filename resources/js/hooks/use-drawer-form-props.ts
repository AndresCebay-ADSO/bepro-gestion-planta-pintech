import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

const LOAD_ERROR =
    'No se pudieron cargar los datos del formulario. Intenta nuevamente.';

/**
 * Carga las opciones de un formulario en cajón (lotes, bodegas…), que el servidor envía como
 * `Inertia::optional(...)->once()`: solo llegan cuando se piden, y el cliente las conserva en las respuestas
 * siguientes, incluida la del 422 de un envío inválido. Sin `once()` esa respuesta llega sin ellas y el cajón
 * desmonta el formulario, con lo digitado y sin mostrar los errores (auditoría 3.6, INV-14 y PT-01).
 *
 * Como el cliente las recuerda, se piden de nuevo en cada apertura, con el esqueleto a la vista, para no ofrecer
 * por un instante los saldos de antes (de un movimiento recién registrado o de otro usuario). No se pierde nada:
 * el cajón descarta el formulario al cerrarse.
 *
 * @param props  Nombres de las props que pide el cajón; debe ser una constante del módulo.
 * @param loaded Si las props ya están en la página.
 */
export function useDrawerFormProps(props: string[], loaded: boolean) {
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);

    const load = useCallback(() => {
        setLoadError(null);
        setIsLoading(true);

        router.reload({
            only: props,
            onFinish: () => setIsLoading(false),
            onNetworkError: () => {
                setLoadError(LOAD_ERROR);

                return false;
            },
            onHttpException: () => {
                setLoadError(LOAD_ERROR);

                return false;
            },
        });
    }, [props]);

    return {
        load,
        isLoading,
        // Si falla un refresco con datos ya cargados, el formulario sigue con los que tiene.
        loadError: loaded ? null : loadError,
    };
}
