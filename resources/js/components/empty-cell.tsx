/**
 * Celda sin dato de una tabla (DESIGN.md §9): «—» atenuado; el lector de pantalla dice «Sin dato», no «—».
 * La usan `<DataListing>` y `<DetailTable>` cuando `cell` devuelve un valor vacío (`isEmptyCell`), y `<DetailItem>`
 * cuando lo es su `value`.
 */
export function EmptyCell() {
    return (
        <>
            <span aria-hidden="true" className="text-muted-foreground">
                —
            </span>
            <span className="sr-only">Sin dato</span>
        </>
    );
}
