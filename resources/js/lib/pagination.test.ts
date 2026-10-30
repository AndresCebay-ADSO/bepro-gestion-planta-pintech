import { describe, expect, it } from 'vitest';

import { paginationSummary } from './pagination';

describe('paginationSummary', () => {
    it('returns null when the listing is empty', () => {
        expect(paginationSummary(null, null, 0)).toBeNull();
        expect(paginationSummary(0, 0, 0)).toBeNull();
        expect(paginationSummary(null, null, 230)).toBeNull();
    });

    it('uses the singular noun when there is a single record', () => {
        expect(paginationSummary(1, 1, 1)).toBe('1 registro');
    });

    it('shows only the count when all records fit on one page', () => {
        expect(paginationSummary(1, 12, 12)).toBe('12 registros');
    });

    it('shows the current range and total across multiple pages', () => {
        expect(paginationSummary(1, 15, 230)).toBe('Mostrando 1–15 de 230');
        expect(paginationSummary(16, 30, 230)).toBe('Mostrando 16–30 de 230');
    });

    it('formats thousands with a dot', () => {
        expect(paginationSummary(1, 1230, 1230)).toBe('1.230 registros');
        expect(paginationSummary(1216, 1230, 1230)).toBe(
            'Mostrando 1.216–1.230 de 1.230',
        );
    });
});
