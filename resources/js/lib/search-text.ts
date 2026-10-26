/**
 * Búsqueda en las listas de selección (`ui/combobox.tsx`): sin tildes, sin mayúsculas, sin espacios y sin signos, para
 * que «epoxica» encuentre «EPÓXICA» y «aba3» encuentre «ABA-3 QC».
 */

/** El texto como se compara: sin tildes ni diéresis (NFD), en minúsculas y solo letras y números. La «ñ» queda «n». */
export function normalizeSearchText(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]/gu, '');
}

export type SearchEntry<T> = { item: T; keys: string[] };

/** Prepara la lista una sola vez: los textos de cada elemento ya normalizados, para no repetirlo en cada tecla. */
export function buildSearchIndex<T>(
    items: readonly T[],
    textsOf: (item: T) => ReadonlyArray<string | null | undefined>,
): SearchEntry<T>[] {
    return items.map((item) => ({
        item,
        keys: textsOf(item)
            .filter((text): text is string => typeof text === 'string')
            .map(normalizeSearchText)
            .filter((key) => key !== ''),
    }));
}

/**
 * Los elementos en los que aparece lo buscado (en cualquiera de sus textos), **en su orden original**. Vacío o solo
 * signos devuelve todos.
 */
export function filterSearchIndex<T>(
    index: readonly SearchEntry<T>[],
    search: string,
): T[] {
    const needle = normalizeSearchText(search);

    return index
        .filter(
            (entry) =>
                needle === '' || entry.keys.some((key) => key.includes(needle)),
        )
        .map((entry) => entry.item);
}
