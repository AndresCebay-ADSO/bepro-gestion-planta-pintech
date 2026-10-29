import { describe, expect, it } from 'vitest';
import { firstErrorStep, stepOfErrorKey } from './paint-development-steps';

describe('stepOfErrorKey', () => {
    it('maps each payload to its step', () => {
        expect(stepOfErrorKey('context_payload.temp_min')).toBe(1);
        expect(stepOfErrorKey('performance_payload.vida_util')).toBe(2);
        expect(stepOfErrorKey('application_payload.dft_max')).toBe(3);
        expect(stepOfErrorKey('specifications_payload.aprobador')).toBe(4);
    });

    it('puts the project fields in the first step', () => {
        expect(stepOfErrorKey('client_name')).toBe(1);
        expect(stepOfErrorKey('sample_due_date')).toBe(1);
    });

    it('maps an array entry by its payload', () => {
        expect(stepOfErrorKey('application_payload.metodo.0')).toBe(3);
    });
});

describe('firstErrorStep', () => {
    it('returns the earliest step with an error, whatever the order of the keys', () => {
        expect(
            firstErrorStep([
                'specifications_payload.aprobador',
                'application_payload.dft_min',
            ]),
        ).toBe(3);
    });

    it('returns undefined without errors', () => {
        expect(firstErrorStep([])).toBeUndefined();
    });
});
