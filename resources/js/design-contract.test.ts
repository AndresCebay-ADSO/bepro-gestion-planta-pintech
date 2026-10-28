import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

import { describe, expect, it } from 'vitest';

/*
 * Guardián de DESIGN.md: lee los archivos como texto (sin DOM) y falla con el archivo, la línea y la regla que se
 * rompió. `components/ui/` (shadcn) queda fuera de las reglas de estilo, pero no de la de textos en español;
 * `pages/welcome.tsx`, que se va a borrar, queda fuera de todas.
 */

const root = process.cwd();

interface Rule {
    pattern: RegExp;
    rule: string;
}

interface Violation {
    location: string;
    match: string;
    rule: string;
}

function filesUnder(dir: string, extensions: string[]): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            return filesUnder(path, extensions);
        }

        return extensions.some((extension) => entry.name.endsWith(extension))
            ? [path]
            : [];
    });
}

function relativePath(path: string): string {
    return relative(root, path).split(sep).join('/');
}

function violations(files: string[], rules: Rule[]): Violation[] {
    return files.flatMap((file) =>
        readFileSync(file, 'utf8')
            .split('\n')
            .flatMap((line, index) =>
                rules.flatMap(({ pattern, rule }) =>
                    [...line.matchAll(pattern)].map((match) => ({
                        location: `${relativePath(file)}:${index + 1}`,
                        match: match[0],
                        rule,
                    })),
                ),
            ),
    );
}

function report(found: Violation[]): string[] {
    return found.map(
        ({ location, match, rule }) => `${location} «${match}» → ${rule}`,
    );
}

const excluded = [
    'resources/js/components/ui/',
    'resources/js/pages/welcome.tsx',
];

const screenFiles = ['pages', 'components', 'layouts']
    .flatMap((dir) => filesUnder(join(root, 'resources/js', dir), ['.tsx']))
    .filter(
        (file) =>
            !excluded.some((prefix) => relativePath(file).startsWith(prefix)),
    );

const fontCdn: Rule = {
    pattern: /fonts\.(?:googleapis\.com|bunny\.net)/g,
    rule: 'DESIGN.md §5: Instrument Sans autohospedada, sin Google Fonts ni bunny',
};

describe('contrato de DESIGN.md', () => {
    it('encuentra las pantallas que vigila', () => {
        expect(screenFiles.length).toBeGreaterThan(50);
    });

    it('solo usa los pesos 400, 500 y 600', () => {
        const found = violations(screenFiles, [
            {
                pattern: /\bfont-(?:bold|extrabold|black)\b/g,
                rule: 'DESIGN.md §5: pesos font-normal, font-medium o font-semibold',
            },
        ]);

        expect(report(found)).toEqual([]);
    });

    it('no usa tamaños arbitrarios en px o rem', () => {
        const found = violations(screenFiles, [
            {
                pattern: /\b(?:text|leading)-\[[\d.]+(?:px|rem)\]/g,
                rule: 'DESIGN.md §5: solo la escala de Tailwind (text-xs, text-sm, leading-5…)',
            },
        ]);

        expect(report(found)).toEqual([]);
    });

    it('no deja en inglés lo que oye un lector de pantalla', () => {
        // Incluye components/ui/: sus textos ocultos (shadcn) también llegan al usuario.
        const files = filesUnder(join(root, 'resources/js'), ['.tsx']).filter(
            (file) => relativePath(file) !== 'resources/js/pages/welcome.tsx',
        );
        const english =
            /\b(?:Toggle|Close|Open|Hide|Show|Previous|Next|More|Loading|Pagination|Settings|Log ?out|Search|Sidebar|Menu|Breadcrumb|Navigation|Notifications|Actions|Select|Submit|Cancel|Save|Delete|Edit|View|Back|Password|Profile|Icon)\b/i;
        const rule =
            'DESIGN.md §1: textos ocultos (sr-only, aria-label, placeholder, title, alt) en español';
        // Textos entre comillas de una expresión JSX; los nombres de variables (`{label}`, `{onClose}`) no cuentan.
        const literals = (expression: string): string[] =>
            expression.match(/(["'`])(?:(?!\1)[\s\S])*\1/g) ?? [];

        const found = files.flatMap((file) => {
            const source = readFileSync(file, 'utf8');
            const lineOf = (index: number) =>
                source.slice(0, index).split('\n').length;
            const hits: { index: number; text: string }[] = [];

            // Contenido completo de un elemento sr-only, hasta su propia etiqueta de cierre, con todo lo que lleve
            // anidado (`<SheetHeader className="sr-only"><SheetTitle>…</SheetTitle><SheetDescription>…`) y
            // expresiones como `{'Cerrar'}`.
            for (const match of source.matchAll(
                /<([\w.]+)\b[^>]*className="[^"]*\bsr-only\b[^"]*"[^>]*>([\s\S]*?)<\/\1>/g,
            )) {
                const content = match[2];
                const plain = content
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\{[^}]*\}/g, ' ');
                const texts = [plain, ...literals(content)];

                if (texts.some((text) => english.test(text))) {
                    hits.push({ index: match.index, text: match[0] });
                }
            }

            // Valor de aria-label, placeholder, title o alt: entre comillas dobles o simples (con o sin espacios junto
            // al `=`: `components/ui/` no pasa por Prettier), o una expresión `{…}` leída hasta su llave de cierre
            // (aunque tenga plantillas `${…}` o ternarios dentro).
            for (const match of source.matchAll(
                /\b(?:aria-label|placeholder|title|alt)\s*=\s*(?:"([^"]*)"|'([^']*)'|\{)/g,
            )) {
                let texts: string[];

                if (match[1] !== undefined || match[2] !== undefined) {
                    texts = [match[1] ?? match[2]];
                } else {
                    let depth = 1;
                    let end = match.index + match[0].length;

                    while (end < source.length && depth > 0) {
                        if (source[end] === '{') {
                            depth++;
                        }

                        if (source[end] === '}') {
                            depth--;
                        }

                        end++;
                    }

                    texts = literals(
                        source.slice(match.index + match[0].length, end - 1),
                    );
                }

                if (texts.some((text) => english.test(text))) {
                    hits.push({ index: match.index, text: match[0] });
                }
            }

            return hits.map(({ index, text }) => ({
                location: `${relativePath(file)}:${lineOf(index)}`,
                match: text.replace(/\s+/g, ' ').slice(0, 120),
                rule,
            }));
        });

        expect(report(found)).toEqual([]);
    });

    it('no carga fuentes de terceros', () => {
        const files = [
            ...screenFiles,
            ...filesUnder(join(root, 'resources/css'), ['.css']),
            ...filesUnder(join(root, 'resources/views'), ['.php']),
        ];

        expect(report(violations(files, [fontCdn]))).toEqual([]);
    });

    it('solo usa h1 visible en PageHeader (y excepciones aprobadas)', () => {
        const h1Exceptions = [
            'resources/js/components/page-header.tsx',
            // Temporal: el banner del dashboard se reemplaza cuando se rediseñe el dashboard.
            'resources/js/components/dashboard/DashboardHeader.tsx',
            'resources/js/pages/auth/',
            'resources/js/layouts/auth/',
            'resources/js/pages/Public/QrLanding/',
            'resources/js/pages/welcome.tsx',
            'resources/js/pages/ErrorPage.tsx',
        ];

        const files = screenFiles.filter(
            (file) =>
                !h1Exceptions.some((prefix) =>
                    relativePath(file).startsWith(prefix),
                ),
        );

        const found = files.flatMap((file) => {
            const source = readFileSync(file, 'utf8');
            const lineOf = (index: number) =>
                source.slice(0, index).split('\n').length;
            const hits: Violation[] = [];

            for (const match of source.matchAll(/<h1\b([\s\S]*?)>/gi)) {
                const attrs = match[1];

                if (!/\bsr-only\b/.test(attrs)) {
                    hits.push({
                        location: `${relativePath(file)}:${lineOf(match.index)}`,
                        match: match[0].replace(/\s+/g, ' ').slice(0, 120),
                        rule: 'DESIGN.md §5: el único h1 visible debe ser <PageHeader>',
                    });
                }
            }

            return hits;
        });

        expect(report(found)).toEqual([]);
    });

    it('pinta los listados con DataListing, sin tablas a mano en pages/', () => {
        // Excepciones por archivo: Costos y Precios no son listados (se rediseñan aparte) y las tablas de detalle
        // esperan su propio plan. Al migrar una, se quita de aquí.
        const tableExceptions = [
            'resources/js/pages/Costs/Index.tsx',
            'resources/js/pages/Formulas/Show.tsx',
            'resources/js/pages/Inventory/RawMaterials/Show.tsx',
            'resources/js/pages/Inventory/Warehouses/Show.tsx',
            'resources/js/pages/Inventory/Warehouses/AssignUsers.tsx',
            'resources/js/pages/Production/ShrinkWraps/Show.tsx',
            'resources/js/pages/Products/Show.tsx',
            'resources/js/pages/QrCodes/Show.tsx',
            'resources/js/pages/Quotations/Show.tsx',
            'resources/js/pages/SalesOrders/Show.tsx',
        ];

        const files = screenFiles.filter(
            (file) =>
                relativePath(file).startsWith('resources/js/pages/') &&
                !tableExceptions.includes(relativePath(file)),
        );

        const found = violations(files, [
            {
                pattern: /<(?:table|Table)\b/g,
                rule: 'DESIGN.md §9: un listado es <DataListing>, no una tabla escrita a mano',
            },
        ]);

        expect(report(found)).toEqual([]);

        // Una excepción que ya no existe o ya no tiene tabla sobra: se quita para que el guardián la vigile.
        const staleExceptions = tableExceptions.filter(
            (path) =>
                !existsSync(join(root, path)) ||
                !/<(?:table|Table)\b/.test(
                    readFileSync(join(root, path), 'utf8'),
                ),
        );

        expect(staleExceptions).toEqual([]);
    });

    it('no importa el componente retirado TableActions', () => {
        const files = filesUnder(join(root, 'resources/js'), ['.ts', '.tsx']);

        const found = violations(files, [
            {
                pattern: /from\s+['"][^'"]*\/table-actions['"]/g,
                rule: 'DESIGN.md §9: las acciones de fila van en <RowActions>',
            },
        ]);

        expect(report(found)).toEqual([]);
    });

    it('no usa h2 de tamaño text-xl o superior', () => {
        const h2Exceptions = [
            'resources/js/pages/auth/',
            'resources/js/layouts/auth/',
            'resources/js/pages/Public/QrLanding/',
        ];

        const files = screenFiles.filter(
            (file) =>
                !h2Exceptions.some((prefix) =>
                    relativePath(file).startsWith(prefix),
                ),
        );

        const found = files.flatMap((file) => {
            const source = readFileSync(file, 'utf8');
            const lineOf = (index: number) =>
                source.slice(0, index).split('\n').length;
            const hits: Violation[] = [];

            for (const match of source.matchAll(/<h2\b([\s\S]*?)>/gi)) {
                const attrs = match[1];

                if (/\btext-(?:xl|[2-9]xl)\b/.test(attrs)) {
                    hits.push({
                        location: `${relativePath(file)}:${lineOf(match.index)}`,
                        match: match[0].replace(/\s+/g, ' ').slice(0, 120),
                        rule: 'DESIGN.md §5: los subtítulos h2 deben ser text-lg (18 px) o menores',
                    });
                }
            }

            return hits;
        });

        expect(report(found)).toEqual([]);
    });

    it('ningún campo hace que iOS acerque la página', () => {
        // Safari de iOS acerca la página al enfocar un campo con letra de menos de 16 px. Una regla de app.css, fuera de
        // @layer (les gana a las utilidades), pone todo campo a 16 px como mínimo en pantallas táctiles, de pie o
        // acostado. Así no depende de que cada pantalla elija bien el tamaño.
        const css = readFileSync(join(root, 'resources/css/app.css'), 'utf8');
        const rule =
            /@media \(any-pointer: coarse\)\s*\{\s*input,\s*select,\s*textarea\s*\{\s*font-size:\s*max\(16px, 1em\);/.exec(
                css,
            );

        expect(
            rule,
            'regla de 16 px para pantallas táctiles en app.css',
        ).not.toBeNull();

        // Fuera de toda capa: las llaves abiertas antes de la regla están todas cerradas.
        const before = css.slice(0, rule?.index ?? 0);
        const depth =
            (before.match(/\{/g) ?? []).length -
            (before.match(/\}/g) ?? []).length;

        expect(depth, 'la regla de 16 px no puede ir dentro de @layer').toBe(0);

        // Y nadie lo «arregla» bloqueando el zoom: en Android eso también impide ampliar con los dedos.
        const layout = readFileSync(
            join(root, 'resources/views/app.blade.php'),
            'utf8',
        );

        expect(layout).not.toMatch(/maximum-scale|user-scalable\s*=\s*["']?no/);
    });
});
