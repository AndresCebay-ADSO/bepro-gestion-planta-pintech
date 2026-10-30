import { describe, expect, it } from 'vitest';
import { formulaCrumb } from './formula-name';

describe('formulaCrumb', () => {
    it('names the product before the version', () => {
        expect(
            formulaCrumb({ version: 2, product: { name: 'Esmalte blanco' } }),
        ).toBe('Esmalte blanco v2');
    });

    it('falls back to the version when there is no product', () => {
        expect(formulaCrumb({ version: 1, product: null })).toBe('v1');
    });
});
