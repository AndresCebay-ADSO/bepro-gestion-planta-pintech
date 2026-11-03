import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { Alert } from '@/components/ui/alert';

describe('Alert: el role solo si se pasa', () => {
    it('no pone role por defecto: un aviso fijo no se anuncia al cargar la pantalla', () => {
        expect(renderToStaticMarkup(createElement(Alert))).not.toContain(
            'role=',
        );
    });

    it('pone el role que se le pasa: lo usa el aviso que aparece de repente', () => {
        expect(
            renderToStaticMarkup(createElement(Alert, { role: 'alert' })),
        ).toContain('role="alert"');
    });
});
