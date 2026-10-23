import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

import { describe, expect, it } from 'vitest';

/*
 * Guardián de DESIGN.md: lee los archivos como texto (sin DOM) y falla con el archivo, la línea y la regla que se
 * rompió. `components/ui/` (shadcn) queda fuera, igual que `pages/welcome.tsx`, que se va a borrar.
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

    it('no carga fuentes de terceros', () => {
        const files = [
            ...screenFiles,
            ...filesUnder(join(root, 'resources/css'), ['.css']),
            ...filesUnder(join(root, 'resources/views'), ['.php']),
        ];

        expect(report(violations(files, [fontCdn]))).toEqual([]);
    });
});
