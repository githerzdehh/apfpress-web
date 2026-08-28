export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

export async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const token = typeof document === 'undefined' ? '' : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const isFormData = options.body instanceof FormData;
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': token,
            ...options.headers,
        },
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = (payload.errors ?? {}) as Record<string, string[]>;
        const firstError = Object.values(errors).flat()[0];
        throw new ApiError(String(firstError ?? payload.message ?? 'The request could not be completed.'), response.status, errors);
    }

    return payload as T;
}

export function money(amount: number, currency = 'CAD'): string {
    return new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount / 100);
}
