import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { EmptyCell } from '@/components/empty-cell';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';

describe('FormattedNumber y FormattedDate: una sola fuente del vacío', () => {
    const emptyCellMarkup = renderToStaticMarkup(createElement(EmptyCell));

    it('sin emptyValue pintan <EmptyCell /> dentro del span del componente', () => {
        expect(
            renderToStaticMarkup(
                createElement(FormattedNumber, { value: null }),
            ),
        ).toBe(
            `<span class="inline-block tabular-nums text-left text-sm">${emptyCellMarkup}</span>`,
        );
        expect(
            renderToStaticMarkup(
                createElement(FormattedNumber, { value: '', currency: true }),
            ),
        ).toBe(
            `<span class="inline-block tabular-nums text-left text-sm">${emptyCellMarkup}</span>`,
        );
        expect(
            renderToStaticMarkup(createElement(FormattedDate, { value: null })),
        ).toBe(`<span>${emptyCellMarkup}</span>`);
        expect(
            renderToStaticMarkup(
                createElement(FormattedDate, { value: 'fecha-invalida' }),
            ),
        ).toBe(`<span>${emptyCellMarkup}</span>`);
    });

    it('conservan las clases del que llama (className, align, size, bold) cuando el valor está vacío', () => {
        const numberMarkup = renderToStaticMarkup(
            createElement(FormattedNumber, {
                value: null,
                currency: true,
                align: 'right',
                size: 'lg',
                bold: true,
                className: 'opacity-50',
            }),
        );
        const dateMarkup = renderToStaticMarkup(
            createElement(FormattedDate, {
                value: null,
                className: 'opacity-50',
            }),
        );

        expect(numberMarkup).toBe(
            `<span class="inline-block tabular-nums text-right text-base font-semibold opacity-50">${emptyCellMarkup}</span>`,
        );
        expect(dateMarkup).toBe(
            `<span class="opacity-50">${emptyCellMarkup}</span>`,
        );
    });

    it('con emptyValue pintan el texto atenuado sin «Sin dato» y conservan las clases del que llama', () => {
        const numberMarkup = renderToStaticMarkup(
            createElement(FormattedNumber, {
                value: null,
                currency: true,
                emptyValue: 'Sin precio',
                className: 'opacity-50',
            }),
        );
        const dateMarkup = renderToStaticMarkup(
            createElement(FormattedDate, {
                value: null,
                emptyValue: 'Sin estimar',
                className: 'opacity-50',
            }),
        );

        expect(numberMarkup).toContain('text-muted-foreground');
        expect(numberMarkup).toContain('opacity-50');
        expect(numberMarkup).toContain('Sin precio');
        expect(numberMarkup).not.toContain('Sin dato');
        expect(dateMarkup).toBe(
            '<span class="text-muted-foreground opacity-50">Sin estimar</span>',
        );
    });
});
