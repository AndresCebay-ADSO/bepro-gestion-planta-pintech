import { readdirSync, readFileSync } from 'node:fs';
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

            // Contenido de un elemento sr-only hasta su primer cierre, con lo que lleve anidado
            // (`<SheetHeader className="sr-only"><SheetTitle>Menú</SheetTitle>`) y expresiones como `{'Cerrar'}`.
            for (const match of source.matchAll(
                /className="[^"]*\bsr-only\b[^"]*"[^>]*>([\s\S]*?)<\//g,
            )) {
                const content = match[1];
                const plain = content
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\{[^}]*\}/g, ' ');
                const texts = [plain, ...literals(content)];

                if (texts.some((text) => english.test(text))) {
                    hits.push({ index: match.index, text: match[0] });
                }
            }

            // Valor de aria-label, placeholder, title o alt: entre comillas, o una expresión `{…}` leída hasta su llave
            // de cierre (aunque tenga plantillas `${…}` o ternarios dentro).
            for (const match of source.matchAll(
                /\b(?:aria-label|placeholder|title|alt)=(?:"([^"]*)"|\{)/g,
            )) {
                let texts: string[];

                if (match[1] !== undefined) {
                    texts = [match[1]];
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
});
