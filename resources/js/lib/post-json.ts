/** fetch no envía el XSRF-TOKEN por su cuenta, como sí hace Axios. */
function xsrfToken(): string {
    const match = document.cookie.match(/(^| )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[2]) : '';
}

/** POST con JSON a una ruta que responde JSON, para lo que el servidor calcula sin cambiar de página. */
export function postJson(
    url: string,
    body: unknown,
    signal?: AbortSignal,
): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
        signal,
    });
}
