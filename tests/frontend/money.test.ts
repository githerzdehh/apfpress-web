import { describe, expect, it } from 'vitest';
import { money } from '../../resources/js/lib/http';

describe('money', () => {
    it('formats database minor units as Canadian dollars', () => {
        expect(money(2040, 'CAD')).toMatch(/20\.40/);
        expect(money(0, 'CAD')).toMatch(/0\.00/);
    });
});
