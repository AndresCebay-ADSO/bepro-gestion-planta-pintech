import { describe, expect, it } from 'vitest';
import {
    batchMovementDateMin,
    isBeforeBatchEntry,
} from './batch-movement-date';

describe('batchMovementDateMin', () => {
    it('uses the batch entry date when the form has no limit of its own', () => {
        expect(batchMovementDateMin('2026-10-01')).toBe('2026-10-01');
    });

    it('keeps the form limit when there is no batch', () => {
        expect(batchMovementDateMin(undefined, '2026-09-28')).toBe(
            '2026-09-28',
        );
        expect(batchMovementDateMin(null, '2026-09-28')).toBe('2026-09-28');
    });

    it('takes the latest of the two limits', () => {
        expect(batchMovementDateMin('2026-10-01', '2026-09-28')).toBe(
            '2026-10-01',
        );
        expect(batchMovementDateMin('2026-08-15', '2026-09-28')).toBe(
            '2026-09-28',
        );
    });

    it('ignores values that are not plant dates', () => {
        expect(batchMovementDateMin('', '2026-09-28')).toBe('2026-09-28');
        expect(batchMovementDateMin('2026-10-01T00:00:00Z', null)).toBe(
            undefined,
        );
        expect(batchMovementDateMin(undefined)).toBe(undefined);
    });
});

describe('isBeforeBatchEntry', () => {
    it('is true when the chosen date is before the batch entry', () => {
        expect(isBeforeBatchEntry('2026-09-30', '2026-10-01')).toBe(true);
    });

    it('is false on the entry day or after it', () => {
        expect(isBeforeBatchEntry('2026-10-01', '2026-10-01')).toBe(false);
        expect(isBeforeBatchEntry('2026-10-28', '2026-10-01')).toBe(false);
    });

    it('is false without a batch or without a date', () => {
        expect(isBeforeBatchEntry('2026-09-30', undefined)).toBe(false);
        expect(isBeforeBatchEntry('2026-09-30', null)).toBe(false);
        expect(isBeforeBatchEntry('', '2026-10-01')).toBe(false);
        expect(isBeforeBatchEntry(undefined, '2026-10-01')).toBe(false);
    });
});
