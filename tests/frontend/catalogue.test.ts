import { describe, expect, it } from 'vitest';
import { cadToCents, catalogPayload, centsToCad, newCatalogForm, normalizeCatalogForm, payloadFingerprint } from '../../resources/js/lib/catalogue';

describe('catalogue form contract', () => {
    it('uses CAD dollars in the form and exact cents in the API payload', () => {
        expect(centsToCad(2499)).toBe('24.99');
        expect(cadToCents('24.99')).toBe(2499);
        expect(cadToCents('24.9')).toBe(2490);
        expect(cadToCents('')).toBeNull();
        expect(() => cadToCents('24.999')).toThrow(/two decimal/i);

        const form = newCatalogForm();
        form.offerings[0].price_cad = '31.50';
        expect((catalogPayload(form).offerings as any[])[0].price_amount).toBe(3150);
    });

    it('normalizes legacy ISO timestamps before binding a native date input', () => {
        const form = normalizeCatalogForm({
            title: 'Date-safe title',
            offerings: [{
                kind: 'print_book', price_amount: 2000,
                edition: { publication_date: '2026-08-28T00:00:00.000000Z' },
                inventory: {},
            }],
        });

        expect(form.offerings[0].edition.publication_date).toBe('2026-08-28');
        expect(form.offerings[0].price_cad).toBe('20.00');
    });

    it('fingerprints only persistable values for reliable dirty-state checks', () => {
        const form = newCatalogForm();
        const baseline = payloadFingerprint(form);
        form.cover = { id: 1, url: '/cover.jpg', alt_text: 'Cover' };
        expect(payloadFingerprint(form)).toBe(baseline);
        form.title = 'Changed';
        expect(payloadFingerprint(form)).not.toBe(baseline);
    });
});
