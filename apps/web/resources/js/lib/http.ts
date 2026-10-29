export type ApiErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

export class ApiError extends Error {
    public readonly status: number;

    public readonly errors: Record<string, string[]>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

type RequestOptions = {
    method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
    body?: unknown;
    signal?: AbortSignal;
};

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp(
            `(?:^|; )${name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1')}=([^;]*)`,
        ),
    );

    return match ? decodeURIComponent(match[1]) : null;
}

export async function apiRequest<T>(
    url: string,
    options: RequestOptions = {},
): Promise<T> {
    const method = options.method ?? 'GET';
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = readCookie('XSRF-TOKEN');

    if (token !== null) {
        headers['X-XSRF-TOKEN'] = token;
    }

    let body: string | undefined;

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(options.body);
    }

    const response = await fetch(url, {
        method,
        headers,
        body,
        credentials: 'same-origin',
        signal: options.signal,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = (await response.json().catch(() => null)) as
        | ApiErrorPayload
        | T
        | null;

    if (!response.ok) {
        const errorPayload = (payload ?? {}) as ApiErrorPayload;

        throw new ApiError(
            errorPayload.message ??
                `Request failed with status ${response.status}`,
            response.status,
            errorPayload.errors ?? {},
        );
    }

    return payload as T;
}
