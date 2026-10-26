import { describe, expect, it } from 'vitest';

import {
    buildSearchIndex,
    filterSearchIndex,
    normalizeSearchText,
} from '@/lib/search-text';

describe('normalizeSearchText: el texto como se compara', () => {
    it.each([
        ['EPÓXICA', 'epoxica'],
        ['Pintura Acrílica', 'pinturaacrilica'],
        ['Ñandú', 'nandu'],
        ['  MP - 001  ', 'mp001'],
        ['ABA-3 QC', 'aba3qc'],
        ['Galón\t5 L', 'galon5l'],
        ['60% sólidos (gris)', '60solidosgris'],
        ['', ''],
    ])('%j → %j', (text, expected) => {
        expect(normalizeSearchText(text)).toBe(expected);
    });
});

type Option = { id: number; label: string; description?: string };

const options: Option[] = [
    { id: 1, label: 'ABA-3 QC' },
    { id: 2, label: 'PINTURA EPÓXICA', description: 'Galón 5 L' },
    { id: 3, label: 'MP-001', description: 'Resina alquídica' },
    { id: 4, label: 'ABA-30' },
];

const index = buildSearchIndex(options, (option) => [
    option.label,
    option.description,
]);

const ids = (search: string) =>
    filterSearchIndex(index, search).map((option) => option.id);

describe('filterSearchIndex: las opciones que coinciden con lo buscado', () => {
    it('encuentra sin tildes ni mayúsculas', () => {
        expect(ids('epoxica')).toEqual([2]);
        expect(ids('ÉPOX')).toEqual([2]);
    });

    it('ignora espacios, guiones y otros signos', () => {
        expect(ids('aba3')).toEqual([1, 4]);
        expect(ids('aba 3 qc')).toEqual([1]);
        expect(ids('mp001')).toEqual([3]);
    });

    it('busca también en la descripción', () => {
        expect(ids('resina')).toEqual([3]);
        expect(ids('galon5')).toEqual([2]);
    });

    it('no une la etiqueta con la descripción', () => {
        expect(ids('epoxicagalon')).toEqual([]);
    });

    it('vacío, solo espacios o solo signos devuelve todas, en su orden', () => {
        expect(ids('')).toEqual([1, 2, 3, 4]);
        expect(ids('   ')).toEqual([1, 2, 3, 4]);
        expect(ids('-')).toEqual([1, 2, 3, 4]);
    });

    it('conserva el orden original al buscar y al borrar la búsqueda', () => {
        expect(ids('a')).toEqual([1, 2, 3, 4]);
        expect(ids('ab')).toEqual([1, 4]);
        expect(ids('')).toEqual([1, 2, 3, 4]);
    });

    it('no encuentra lo que no está', () => {
        expect(ids('xilol')).toEqual([]);
    });
});
