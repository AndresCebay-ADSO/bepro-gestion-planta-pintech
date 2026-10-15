import { describe, expect, it } from 'vitest';
import { dateBoundKeepingSaved } from './date-time-helpers';

describe('dateBoundKeepingSaved', () => {
    it('drops the bound while the field keeps the saved date', () => {
        expect(
            dateBoundKeepingSaved('2026-09-01', '2026-09-01', '2026-10-13'),
        ).toBeUndefined();
    });

    it('applies the bound once the user picks another date', () => {
        expect(
            dateBoundKeepingSaved('2026-09-02', '2026-09-01', '2026-10-13'),
        ).toBe('2026-10-13');
    });

    it('applies the bound when there is no saved date', () => {
        expect(dateBoundKeepingSaved('2026-10-13', null, '2026-10-13')).toBe(
            '2026-10-13',
        );
        expect(dateBoundKeepingSaved('', '', '2026-10-13')).toBe('2026-10-13');
    });

    it('returns no bound when there is none to apply', () => {
        expect(
            dateBoundKeepingSaved('2026-10-20', '2026-10-13', null),
        ).toBeUndefined();
    });
});
