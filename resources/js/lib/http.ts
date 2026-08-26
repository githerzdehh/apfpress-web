export async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            ...options.headers,
        },
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(String(firstError ?? payload.message ?? 'The request could not be completed.'));
    }

    return payload as T;
}

export function money(amount: number, currency = 'CAD'): string {
    return new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount / 100);
}
