import { useEffect, useState } from 'react';

/** Por qué lo que se ve no corresponde a lo escrito (null si está al día). */
export type PreviewStaleReason = 'invalid' | 'failed' | null;

/** Si la respuesta tarda más que esto, se muestra «Calculando…»; antes no, para que no parpadee. */
const LOADING_INDICATOR_DELAY_MS = 180;

export type ServerPreviewRequest<TBody, TContext> = {
    url: string;
    body: TBody;
    /**
     * Lo que identifica la petición sin viajar al servidor. Se devuelve junto con la respuesta o los errores, para
     * saber a qué datos corresponden, y un cambio aquí también pide otra vista previa.
     */
    context: TContext;
};

/** Primer mensaje de cada campo de un 422 de Laravel. */
export type PreviewErrors = Record<string, string>;

type Answered<TValue, TContext> = { value: TValue; context: TContext };

/** fetch no envía el XSRF-TOKEN por su cuenta, como sí hace Axios. */
function xsrfToken(): string {
    const match = document.cookie.match(/(^| )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[2]) : '';
}

/**
 * Vista previa calculada en el servidor: espera a que el usuario deje de escribir, cancela la petición anterior si
 * llega otra y avisa cuando lo que se ve no está al día. El front no calcula dinero ni costos: muestra lo que responde
 * el servidor con la misma regla con que guarda. Sin petición (`null`) no pide nada y conserva la última respuesta.
 */
export function useServerPreview<TResult, TBody, TContext>(
    request: ServerPreviewRequest<TBody, TContext> | null,
    delayMs: number,
) {
    const [result, setResult] = useState<Answered<TResult, TContext> | null>(
        null,
    );
    const [errors, setErrors] = useState<Answered<
        PreviewErrors,
        TContext
    > | null>(null);
    const [loading, setLoading] = useState(false);
    // Si la última petición falló, lo que se ve es de la última respuesta correcta: 'invalid' si el servidor rechazó
    // los datos (422), 'failed' por cualquier otro fallo (límite de peticiones, error, sin conexión).
    const [stale, setStale] = useState<PreviewStaleReason>(null);

    // La petición como texto: el efecto solo corre cuando cambia lo que se manda, no en cada render.
    const requestKey = request === null ? null : JSON.stringify(request);

    useEffect(() => {
        if (requestKey === null) {
            return;
        }

        const { url, body, context } = JSON.parse(
            requestKey,
        ) as ServerPreviewRequest<TBody, TContext>;
        const controller = new AbortController();
        let loadingIndicatorId: number | null = null;

        const timeoutId = window.setTimeout(async () => {
            loadingIndicatorId = window.setTimeout(
                () => setLoading(true),
                LOADING_INDICATOR_DELAY_MS,
            );

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                    signal: controller.signal,
                });

                if (response.status === 422) {
                    const payload = (await response.json()) as {
                        errors?: Record<string, string[]>;
                    };

                    setErrors({
                        value: Object.fromEntries(
                            Object.entries(payload.errors ?? {}).map(
                                ([field, messages]) => [field, messages[0]],
                            ),
                        ),
                        context,
                    });
                    setStale('invalid');

                    return;
                }

                if (!response.ok) {
                    setErrors(null);
                    setStale('failed');

                    return;
                }

                setResult({
                    value: (await response.json()) as TResult,
                    context,
                });
                setErrors(null);
                setStale(null);
            } catch (error) {
                // Una petición cancelada (el usuario siguió escribiendo) no es un fallo: la reemplaza la siguiente.
                if ((error as Error).name !== 'AbortError') {
                    setErrors(null);
                    setStale('failed');
                }
            } finally {
                if (loadingIndicatorId !== null) {
                    window.clearTimeout(loadingIndicatorId);
                }

                setLoading(false);
            }
        }, delayMs);

        return () => {
            controller.abort();

            if (loadingIndicatorId !== null) {
                window.clearTimeout(loadingIndicatorId);
            }

            window.clearTimeout(timeoutId);
            setLoading(false);
        };
    }, [requestKey, delayMs]);

    return { result, errors, loading, stale };
}
