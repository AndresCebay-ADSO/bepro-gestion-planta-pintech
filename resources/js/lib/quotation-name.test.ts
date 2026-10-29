import { describe, expect, it } from 'vitest';
import { quotationName } from './quotation-name';

describe('quotationName', () => {
    it('uses the quotation number, with a space after «No.»', () => {
        expect(quotationName({ id: 12, quotation_number: 57 })).toBe(
            'Cotización No. 57',
        );
    });

    it('falls back to the id when there is no number', () => {
        expect(quotationName({ id: 12, quotation_number: null })).toBe(
            'Cotización No. 12',
        );
    });
});
