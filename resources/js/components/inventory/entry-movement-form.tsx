import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ChangeEvent } from 'react';
import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { PlantDateInput } from '@/components/plant-date-input';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { SheetBody, SheetFooter } from '@/components/ui/sheet';
import { NUMBER_TYPES } from '@/lib/number-types';
import { idPayload, toDecimalPayload } from '@/lib/numeric-input';
import { store as inventoryMovementsStore } from '@/routes/inventory-movements';
import type { InventoryOption, SupplierOption } from '@/types';
import { MovementFormBase } from './movement-form-base';

type Props = {
    rawMaterials: InventoryOption[];
    batches: InventoryOption[];
    warehouses: InventoryOption[];
    suppliers: SupplierOption[];
    defaultWarehouseId?: number;
    /** Fecha más vieja que admite una entrada (D1b); la valida el servidor. */
    oldestEntryDate?: string;
    onSuccess?: () => void;
    onCancel: () => void;
};

export function EntryMovementForm({
    rawMaterials,
    batches,
    warehouses,
    suppliers,
    defaultWarehouseId,
    oldestEntryDate,
    onSuccess,
    onCancel,
}: Props) {
    const { plantToday } = usePage().props;
    const form = useForm<{
        raw_material_id: string;
        warehouse_id: string;
        batch_id: string;
        type: 'entry';
        quantity: string;
        cost_price: string;
        lot_number: string;
        supplier_id: string;
        expiry_date: string;
        movement_date: string;
        notes: string;
    }>({
        raw_material_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        batch_id: '',
        type: 'entry',
        quantity: '',
        cost_price: '',
        lot_number: '',
        supplier_id: '',
        expiry_date: '',
        movement_date: plantToday,
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            raw_material_id: idPayload(data.raw_material_id),
            warehouse_id: idPayload(data.warehouse_id),
            batch_id: idPayload(data.batch_id),
            quantity: toDecimalPayload(data.quantity),
            cost_price:
                data.cost_price === ''
                    ? null
                    : toDecimalPayload(data.cost_price),
            lot_number: data.batch_id === '' ? data.lot_number : null,
            supplier_id:
                data.batch_id === '' ? idPayload(data.supplier_id) : null,
            expiry_date:
                data.batch_id === '' && data.expiry_date !== ''
                    ? data.expiry_date
                    : null,
        }));

        form.post(inventoryMovementsStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    };

    const selectedBatch = form.data.batch_id
        ? batches.find((b) => String(b.id) === form.data.batch_id)
        : undefined;
    const batchHasPrice =
        selectedBatch?.unit_price !== undefined &&
        selectedBatch?.unit_price !== null &&
        selectedBatch.unit_price !== '';
    const isCostReadOnly = Boolean(form.data.batch_id !== '' && batchHasPrice);

    return (
        <form
            noValidate
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col"
        >
            <SheetBody>
                <MovementFormBase
                    form={form}
                    rawMaterials={rawMaterials}
                    batches={batches}
                    warehouses={warehouses}
                    type="entry"
                    movementDateMin={oldestEntryDate}
                    beforeDate={
                        <FormField
                            id="cost_price"
                            label="Precio de costo (u)"
                            required={!isCostReadOnly}
                            help={
                                isCostReadOnly
                                    ? 'Costo fijado por el lote seleccionado.'
                                    : undefined
                            }
                            error={form.errors.cost_price}
                        >
                            <DecimalInput
                                {...NUMBER_TYPES.money}
                                value={form.data.cost_price}
                                onValueChange={(value) => {
                                    if (!isCostReadOnly) {
                                        form.setData('cost_price', value);
                                    }
                                }}
                                readOnly={isCostReadOnly}
                                className={
                                    isCostReadOnly
                                        ? 'cursor-not-allowed bg-muted/50'
                                        : ''
                                }
                                placeholder="0,00"
                            />
                        </FormField>
                    }
                >
                    {/* Lote nuevo: número y vencimiento en una fila; el proveedor, que suele ser largo, a lo ancho. */}
                    {form.data.batch_id === '' && (
                        <>
                            <FormField
                                id="lot_number"
                                label="Número de lote"
                                required
                                error={form.errors.lot_number}
                            >
                                <Input
                                    maxLength={50}
                                    value={form.data.lot_number}
                                    onChange={(
                                        e: ChangeEvent<HTMLInputElement>,
                                    ) =>
                                        form.setData(
                                            'lot_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ej. LOT-2026-001"
                                />
                            </FormField>

                            <FormField
                                id="expiry_date"
                                label="Fecha de vencimiento"
                                error={form.errors.expiry_date}
                            >
                                <PlantDateInput
                                    clearable
                                    min={form.data.movement_date}
                                    placeholder="Sin vencimiento"
                                    value={form.data.expiry_date}
                                    onChange={(value) =>
                                        form.setData('expiry_date', value)
                                    }
                                />
                            </FormField>

                            <FormField
                                id="supplier_id"
                                label="Proveedor"
                                required
                                error={form.errors.supplier_id}
                                className="md:col-span-2"
                            >
                                <Combobox
                                    options={suppliers.map((supplier) => ({
                                        id: supplier.id,
                                        label: supplier.name,
                                    }))}
                                    value={form.data.supplier_id}
                                    onChange={(value) =>
                                        form.setData(
                                            'supplier_id',
                                            String(value),
                                        )
                                    }
                                    placeholder="Seleccionar proveedor..."
                                    // Con proveedores, una búsqueda sin coincidencias sigue diciendo «Sin resultados».
                                    emptyText={
                                        suppliers.length === 0
                                            ? 'No hay proveedores activos. Créalos en Configuración → Catálogos.'
                                            : undefined
                                    }
                                />
                            </FormField>
                        </>
                    )}
                </MovementFormBase>
            </SheetBody>

            <SheetFooter>
                <FormActions
                    submitLabel="Registrar entrada"
                    processing={form.processing}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </form>
    );
}
