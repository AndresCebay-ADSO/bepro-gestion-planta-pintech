import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { DetailItem, DetailLabel, DetailList } from '@/components/detail-list';
import { EmptyCell } from '@/components/empty-cell';
import { CardTitle } from '@/components/ui/card';

const emptyCellMarkup = renderToStaticMarkup(createElement(EmptyCell));

function renderItem(props: Partial<Parameters<typeof DetailItem>[0]>): string {
    return renderToStaticMarkup(
        createElement(DetailItem, { label: 'Notas', value: null, ...props }),
    );
}

describe('DetailItem', () => {
    it.each([
        ['null', null],
        ['undefined', undefined],
        ['false', false],
        ['texto vacío', ''],
    ])('pinta <EmptyCell /> con %s', (_name, value) => {
        expect(renderItem({ value })).toContain(emptyCellMarkup);
    });

    it('el 0 es un dato', () => {
        const markup = renderItem({ value: 0 });

        expect(markup).toContain('>0</dd>');
        expect(markup).not.toContain(emptyCellMarkup);
    });

    it('un nodo es un dato y se pinta tal cual', () => {
        expect(
            renderItem({ value: createElement('strong', null, 'Activa') }),
        ).toContain('<strong>Activa</strong>');
    });

    it('span="full" ocupa toda la fila; sin él, solo su columna', () => {
        expect(renderItem({ span: 'full' })).toContain('col-span-full');
        expect(renderItem({})).not.toContain('col-span-full');
    });

    it('valueClassName llega al dato', () => {
        expect(renderItem({ valueClassName: 'font-mono' })).toMatch(
            /<dd class="[^"]*font-mono[^"]*">/,
        );
    });

    it('un valor largo no desborda su columna', () => {
        const markup = renderItem({ value: 'x' });

        expect(markup).toMatch(/<div class="[^"]*min-w-0[^"]*">/);
        expect(markup).toMatch(/<dd class="[^"]*break-words[^"]*">/);
    });
});

describe('DetailLabel', () => {
    it('es un <dt> sin mayúsculas', () => {
        const markup = renderToStaticMarkup(
            createElement(DetailLabel, null, 'Creada por'),
        );

        expect(markup).toBe(
            '<dt class="text-xs font-medium text-muted-foreground">Creada por</dt>',
        );
        expect(markup).not.toContain('uppercase');
    });
});

describe('DetailList', () => {
    it.each([
        [undefined, 'grid grid-cols-1 gap-4 md:grid-cols-2'],
        [1, 'grid grid-cols-1 gap-4'],
        [2, 'grid grid-cols-1 gap-4 md:grid-cols-2'],
        [3, 'grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3'],
        [4, 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4'],
    ] as const)(
        'columns=%s pinta las clases de su grilla',
        (columns, classes) => {
            expect(
                renderToStaticMarkup(
                    createElement(DetailList, { columns, children: 'x' }),
                ),
            ).toBe(`<dl class="${classes}">x</dl>`);
        },
    );
});

describe('CardTitle', () => {
    it('es un h2', () => {
        expect(
            renderToStaticMarkup(createElement(CardTitle, null, 'Ficha')),
        ).toMatch(/^<h2 [^>]*class="text-lg font-semibold"[^>]*>Ficha<\/h2>$/);
    });
});
