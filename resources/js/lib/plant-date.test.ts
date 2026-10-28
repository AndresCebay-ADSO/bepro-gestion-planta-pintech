import { describe, expect, it } from 'vitest';
import {
    isPlantDate,
    isPlantDateSelectable,
    parsePlantDate,
    plantDateBounds,
    plantDateInitialMonth,
    plantDateMonthRange,
    toPlantDate,
} from './plant-date';

const today = '2026-10-28';

describe('isPlantDate y parsePlantDate', () => {
    it('accepts a real calendar day', () => {
        expect(isPlantDate('2026-10-28')).toBe(true);
        expect(isPlantDate('2028-02-29')).toBe(true);
    });

    it('rejects empty values, other formats and days that do not exist', () => {
        expect(isPlantDate('')).toBe(false);
        expect(isPlantDate(null)).toBe(false);
        expect(isPlantDate(undefined)).toBe(false);
        expect(isPlantDate('28/10/2026')).toBe(false);
        expect(isPlantDate('2026-10-28T10:00:00Z')).toBe(false);
        expect(isPlantDate('2026-02-30')).toBe(false);
        expect(isPlantDate('2026-13-01')).toBe(false);
    });

    it('parses to local midnight of the same day, with no UTC shift', () => {
        const date = parsePlantDate('2026-01-01');

        expect(date?.getFullYear()).toBe(2026);
        expect(date?.getMonth()).toBe(0);
        expect(date?.getDate()).toBe(1);
        expect(parsePlantDate('2026-02-30')).toBeUndefined();
    });

    it('turns a picked day back into a plant date', () => {
        expect(toPlantDate(new Date(2026, 0, 5))).toBe('2026-01-05');
        expect(toPlantDate(parsePlantDate('2026-12-31')!)).toBe('2026-12-31');
    });
});

describe('plantDateBounds', () => {
    it('has no bounds by default', () => {
        expect(plantDateBounds({ today })).toEqual({});
    });

    it('notFuture caps at today and notPast starts at today', () => {
        expect(plantDateBounds({ today, notFuture: true })).toEqual({
            max: today,
        });
        expect(plantDateBounds({ today, notPast: true })).toEqual({
            min: today,
        });
    });

    it('keeps the stricter bound when min/max and notPast/notFuture meet', () => {
        expect(
            plantDateBounds({ today, min: '2026-09-28', notFuture: true }),
        ).toEqual({ min: '2026-09-28', max: today });
        expect(
            plantDateBounds({ today, max: '2026-12-31', notFuture: true }),
        ).toEqual({ max: today });
        expect(
            plantDateBounds({ today, max: '2026-10-01', notFuture: true }),
        ).toEqual({ max: '2026-10-01' });
        expect(
            plantDateBounds({ today, min: '2026-11-15', notPast: true }),
        ).toEqual({ min: '2026-11-15' });
        expect(
            plantDateBounds({ today, min: '2026-10-01', notPast: true }),
        ).toEqual({ min: today });
    });

    it('ignores a bound that is not a date (the server still validates)', () => {
        expect(plantDateBounds({ today, min: '', max: 'mañana' })).toEqual({});
        expect(plantDateBounds({ today, min: null, max: undefined })).toEqual(
            {},
        );
    });
});

describe('isPlantDateSelectable', () => {
    const bounds = { min: '2026-09-28', max: today };

    it('allows the days inside the bounds, both ends included', () => {
        expect(isPlantDateSelectable('2026-09-28', bounds)).toBe(true);
        expect(isPlantDateSelectable('2026-10-10', bounds)).toBe(true);
        expect(isPlantDateSelectable(today, bounds)).toBe(true);
    });

    it('blocks the days outside the bounds', () => {
        expect(isPlantDateSelectable('2026-09-27', bounds)).toBe(false);
        expect(isPlantDateSelectable('2026-10-29', bounds)).toBe(false);
    });

    it('lets the user keep the saved date even when it is out of bounds', () => {
        expect(
            isPlantDateSelectable('2026-08-01', { min: today }, '2026-08-01'),
        ).toBe(true);
        expect(
            isPlantDateSelectable('2026-08-02', { min: today }, '2026-08-01'),
        ).toBe(false);
    });

    it('allows any day without bounds', () => {
        expect(isPlantDateSelectable('2000-01-01', {})).toBe(true);
        expect(isPlantDateSelectable('2099-12-31', {}, null)).toBe(true);
    });
});

describe('plantDateMonthRange', () => {
    it('goes from January 2021 to ten years ahead without bounds', () => {
        expect(plantDateMonthRange({}, today)).toEqual({
            startMonth: '2021-01',
            endMonth: '2036-12',
        });
    });

    it('stops at the months of the bounds', () => {
        expect(
            plantDateMonthRange({ min: '2026-09-28', max: today }, today),
        ).toEqual({ startMonth: '2026-09', endMonth: '2026-10' });
        expect(plantDateMonthRange({ min: today }, today)).toEqual({
            startMonth: '2026-10',
            endMonth: '2036-12',
        });
    });

    it('widens the range so the value and the saved date can be seen', () => {
        expect(
            plantDateMonthRange({ min: today }, today, '2024-05-01', ''),
        ).toEqual({ startMonth: '2024-05', endMonth: '2036-12' });
        expect(
            plantDateMonthRange({ max: today }, today, null, '2030-01-01'),
        ).toEqual({ startMonth: '2021-01', endMonth: '2030-01' });
        expect(plantDateMonthRange({}, today, '2019-03-01')).toEqual({
            startMonth: '2019-03',
            endMonth: '2036-12',
        });
    });

    it('ignores values that are not dates', () => {
        expect(plantDateMonthRange({ min: today }, today, 'ayer')).toEqual({
            startMonth: '2026-10',
            endMonth: '2036-12',
        });
    });
});

describe('plantDateInitialMonth', () => {
    it('opens on the month of the value', () => {
        expect(plantDateInitialMonth('2025-03-14', {}, today)).toBe('2025-03');
    });

    it('opens on the current month without a value', () => {
        expect(plantDateInitialMonth('', {}, today)).toBe('2026-10');
    });

    it('opens inside the bounds when today is outside them', () => {
        expect(plantDateInitialMonth('', { min: '2026-12-05' }, today)).toBe(
            '2026-12',
        );
        expect(plantDateInitialMonth(null, { max: '2026-06-30' }, today)).toBe(
            '2026-06',
        );
    });
});
