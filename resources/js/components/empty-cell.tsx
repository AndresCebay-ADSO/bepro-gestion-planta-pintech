/**
 * Celda sin dato de una tabla (DESIGN.md §9): «—» atenuado; el lector de pantalla dice «Sin dato», no «—».
 * La usan `<DataListing>` y `<DetailTable>` cuando `cell` devuelve un valor vacío (`isEmptyCell`).
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
