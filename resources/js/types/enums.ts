/** Tono de una pastilla de estado (DESIGN.md §4). Espejo de `App\Enums\Tone`. */
export type Tone = 'neutral' | 'info' | 'warning' | 'success' | 'danger';

/**
 * Enums con tono que llegan en la prop compartida `enums`. Se mantiene a mano junto a `App\Support\ToneEnums::ALL`:
 * al registrar un enum allí, agregar aquí su nombre corto.
 */
export type ToneEnumName =
    | 'AlertSeverity'
    | 'AlertType'
    | 'FinishedInventoryMovementReason'
    | 'InventoryMovementType'
    | 'PaintDevelopmentRequestStatus'
    | 'ProductionOrderStatus'
    | 'QuotationStatus'
    | 'RemnantStatus'
    | 'SalesOrderPriority'
    | 'SalesOrderStatus'
    | 'WarehouseType';

/** Un caso de un enum: la etiqueta en español y su tono. */
export type EnumCase = {
    label: string;
    tone: Tone;
};

/** Prop compartida `enums`: `enums.SalesOrderStatus.pending` → `{ label: 'Pendiente', tone: 'warning' }`. */
export type SharedEnums = Record<ToneEnumName, Record<string, EnumCase>>;
