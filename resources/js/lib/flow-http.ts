export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    const xsrfToken = xsrfCookie
        ? decodeURIComponent(xsrfCookie.substring('XSRF-TOKEN='.length))
        : undefined;
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        },
        body: JSON.stringify(body),
    });
    const payload = (await response.json()) as T & { message?: string };
    if (!response.ok) {
        throw new Error(
            payload.message ?? 'Não foi possível concluir esta ação.',
        );
    }

    return payload;
}
