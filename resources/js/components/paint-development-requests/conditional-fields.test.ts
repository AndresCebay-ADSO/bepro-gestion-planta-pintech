import { describe, expect, it } from 'vitest';

import { payloadFieldApplies } from '@/components/paint-development-requests/conditional-fields';

describe('payloadFieldApplies: campos condicionales de la solicitud de I+D', () => {
    it.each([
        ['otro_sustrato', 'sustrato', 'Otro', 'Acero'],
        ['sistema_existente', 'pintura_existente', 'Sí', 'No'],
        ['otra_tecnologia', 'tecnologia', 'Otra', 'Epóxica'],
    ])(
        '%s aplica solo según %s',
        (field, source, requiredValue, otherValue) => {
            expect(
                payloadFieldApplies(field, { [source]: requiredValue }),
            ).toBe(true);
            expect(payloadFieldApplies(field, { [source]: otherValue })).toBe(
                false,
            );
            expect(payloadFieldApplies(field, {})).toBe(false);
        },
    );

    it('los demás campos siempre aplican, aunque estén vacíos', () => {
        expect(payloadFieldApplies('quimicos', { quimicos: '' })).toBe(true);
        expect(payloadFieldApplies('temp_min', {})).toBe(true);
    });
});
