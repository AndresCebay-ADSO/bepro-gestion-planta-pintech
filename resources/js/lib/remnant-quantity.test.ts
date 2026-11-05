import { describe, expect, it } from 'vitest';

import { remnantGallonsForDisplay } from '@/lib/remnant-quantity';

describe('remnantGallonsForDisplay', () => {
    it('divide los kilos entre el peso por galón', () => {
        expect(remnantGallonsForDisplay('2,5', '4,7')).toBeCloseTo(0.5319, 4);
        expect(remnantGallonsForDisplay(3, '4.5')).toBeCloseTo(0.6667, 4);
    });

    it('sin peso por galón no hay galones', () => {
        expect(remnantGallonsForDisplay('2,5', '')).toBeNull();
        expect(remnantGallonsForDisplay('2,5', null)).toBeNull();
    });

    it('con 0 no hay galones', () => {
        expect(remnantGallonsForDisplay('0', '4,7')).toBeNull();
        expect(remnantGallonsForDisplay('', '4,7')).toBeNull();
        expect(remnantGallonsForDisplay('2,5', '0')).toBeNull();
    });
});
