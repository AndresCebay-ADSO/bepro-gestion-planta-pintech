import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    index as formulasIndex,
    store as formulasStore,
} from '@/routes/formulas';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; code: string; name: string };
type UnitOption = { id: number; name: string; symbol: string };

type DetailRow = {
    raw_material_id: string;
    quantity: string;
    unit_of_measure_id: string;
};

type FormulaForm = {
    product_id: string;
    notes: string;
    details: DetailRow[];
};

type Props = {
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
    selectedProductId?: string | null;
};

const emptyDetail = (): DetailRow => ({
    raw_material_id: '',
    quantity: '',
    unit_of_measure_id: '',
});

export default function FormulasCreate({
    products,
    rawMaterials,
    units,
    selectedProductId,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<FormulaForm>({
        product_id: selectedProductId ?? '',
        notes: '',
        details: [emptyDetail()],
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(formulasStore().url);
    };

    const addDetail = () => {
        setData('details', [...data.details, emptyDetail()]);
    };

    const removeDetail = (index: number) => {
        setData(
            'details',
            data.details.filter((_, i) => i !== index),
        );
    };

    const updateDetail = (
        index: number,
        field: keyof DetailRow,
        value: string,
    ) => {
        const updated = data.details.map((detail, i) =>
            i === index ? { ...detail, [field]: value } : detail,
        );
        setData('details', updated);
    };

    return (
        <>
            <Head title="Nueva Fórmula" />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={formulasIndex().url}
                            className="hover:text-foreground"
                        >
                            Fórmulas
                        </Link>
                        <span>/</span>
                        <span>Nueva</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Nueva Fórmula
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Define los ingredientes necesarios para producir 1 galón del producto.
                        Esta fórmula se usará para calcular consumos en órdenes de producción.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Encabezado */}
                    <div className="max-w-2xl space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">
                            Información General
                        </h2>

                        <div className="space-y-2">
                            <Label htmlFor="product_id">Producto *</Label>
                            <Select
                                value={data.product_id}
                                onValueChange={(v) => setData('product_id', v)}
                            >
                                <SelectTrigger id="product_id">
                                    <SelectValue placeholder="Selecciona el producto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {products.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={String(p.id)}
                                        >
                                            <span className="font-mono">
                                                {p.code}
                                            </span>{' '}
                                            — {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.product_id && (
                                <p className="text-sm text-destructive">
                                    {errors.product_id}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notas / Observaciones</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Ej: Fórmula para tono azul celeste estándar, sin modificaciones..."
                                className="min-h-[80px]"
                            />
                            {errors.notes && (
                                <p className="text-sm text-destructive">
                                    {errors.notes}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Ingredientes */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <div>
                                <h2 className="font-medium text-foreground">
                                    Ingredientes por Galón
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Cantidades de materias primas para producir exactamente 1 galón.
                                    Ejemplo: 1.5 kg de resina por galón de esmalte.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addDetail}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Agregar ingrediente
                            </Button>
                        </div>

                        <div className="divide-y divide-border">
                            {data.details.map((detail, index) => (
                                <div
                                    key={index}
                                    className="grid grid-cols-12 items-end gap-3 p-4"
                                >
                                    <div className="col-span-5 space-y-1">
                                        {index === 0 && (
                                            <Label className="text-xs text-muted-foreground">
                                                Materia Prima
                                            </Label>
                                        )}
                                        <Select
                                            value={detail.raw_material_id}
                                            onValueChange={(v) =>
                                                updateDetail(
                                                    index,
                                                    'raw_material_id',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecciona..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {rawMaterials.map((rm) => (
                                                    <SelectItem
                                                        key={rm.id}
                                                        value={String(rm.id)}
                                                    >
                                                        <span className="font-mono">
                                                            {rm.code}
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {(errors as Record<string, string>)[
                                            `details.${index}.raw_material_id`
                                        ] && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `details.${index}.raw_material_id`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>

                                    <div className="col-span-3 space-y-1">
                                        {index === 0 && (
                                            <Label className="text-xs text-muted-foreground">
                                                Cantidad (por galón)
                                            </Label>
                                        )}
                                        <Input
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            value={detail.quantity}
                                            onChange={(e) =>
                                                updateDetail(
                                                    index,
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Ej: 1.5"
                                        />
                                        {(errors as Record<string, string>)[
                                            `details.${index}.quantity`
                                        ] && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `details.${index}.quantity`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>

                                    <div className="col-span-3 space-y-1">
                                        {index === 0 && (
                                            <Label className="text-xs text-muted-foreground">
                                                Unidad
                                            </Label>
                                        )}
                                        <Select
                                            value={detail.unit_of_measure_id}
                                            onValueChange={(v) =>
                                                updateDetail(
                                                    index,
                                                    'unit_of_measure_id',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Unidad..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((u) => (
                                                    <SelectItem
                                                        key={u.id}
                                                        value={String(u.id)}
                                                    >
                                                        {u.symbol}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="col-span-1 flex items-center justify-end">
                                        {data.details.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    removeDetail(index)
                                                }
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {(errors as Record<string, string>).details && (
                            <p className="px-6 pb-4 text-sm text-destructive">
                                {(errors as Record<string, string>).details}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando…' : 'Crear Fórmula'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={formulasIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
