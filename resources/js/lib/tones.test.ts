import { describe, expect, it } from 'vitest';

import { pillClasses, toneClasses } from '@/lib/tones';

/*
 * `toneClasses` es la única traducción tono → clases (DESIGN.md §4): cinco tonos, solo tokens de `app.css`.
 */

const palette =
    /\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d/;

describe('mapa de tonos', () => {
    it('tiene los cinco tonos de App\\Enums\\Tone y ninguno más', () => {
        expect(Object.keys(toneClasses).sort()).toEqual([
            'danger',
            'info',
            'neutral',
            'success',
            'warning',
        ]);
    });

    it.each(Object.entries(toneClasses))(
        '%s pinta fondo y texto solo con tokens',
        (_tone, classes) => {
            expect(classes).toMatch(/\bbg-\S+/);
            expect(classes).toMatch(/\btext-\S+/);
            expect(classes).not.toMatch(palette);
            expect(classes).not.toMatch(/#[0-9a-f]{3,8}\b|rgb\(|hsl\(|\[/i);
        },
    );

    it('la pastilla tiene la forma de DESIGN.md §4', () => {
        expect(pillClasses.split(' ')).toEqual(
            expect.arrayContaining([
                'rounded-full',
                'px-2',
                'py-0.5',
                'text-xs',
                'font-medium',
            ]),
        );
    });
});
