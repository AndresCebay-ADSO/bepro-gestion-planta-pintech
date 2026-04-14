import { Head, Link, router } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create as formulasCreate,
    show as formulasShow,
} from '@/routes/formulas';
import { index as productsIndex } from '@/routes/products';

type FormulaItem = {
    id: number;
    version: number;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    created_by?: { name: string } | null;
};

type Props = {
    product: {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
        category?: { name: string } | null;
        unit_of_measure?: { name: string; symbol: string } | null;
        current_cost?: string | null;
        current_price?: string | null;
        profit_margin?: string | null;
        formulas?: FormulaItem[];
    };
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function ProductsShow({ product, can }: Props) {
    const handleDelete = () => {
        if (!window.confirm(`¿Eliminar el producto ${product.code}?`)) {
            return;
        }

        router.delete(`/products/${product.id}`);
    };

    const activeFormula = product.formulas?.find((f) => f.is_active);

    return (
        <>
            <Head title={`Producto ${product.code}`} />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Link
                                href={productsIndex().url}
                                className="hover:text-foreground"
                            >
                                Productos
                            </Link>
                            <span>/</span>
                            <span className="font-mono">{product.code}</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">
                                {product.name}
                            </h1>
                            <Badge
                                variant={
                                    product.is_active ? 'default' : 'secondary'
                                }
                            >
                                {product.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">
                            {product.code}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={productsIndex().url}>Volver</Link>
                        </Button>
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={`/products/${product.id}/edit`}>
                                    Editar
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Info del producto */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-3">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Categoría
                        </p>
                        <p className="text-sm text-foreground">
                            {product.category?.name ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Unidad de Medida
                        </p>
                        <p className="text-sm text-foreground">
                            {product.unit_of_measure
                                ? `${product.unit_of_measure.name} (${product.unit_of_measure.symbol})`
                                : '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Precio de Venta
                        </p>
                        <p className="text-sm font-medium text-foreground">
                            {product.current_price
                                ? `$${product.current_price}`
                                : 'No asignado'}
                        </p>
                    </div>
                </div>

                {/* Fórmulas */}
                <div className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                            <h2 className="font-medium text-foreground">
                                Fórmulas
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {activeFormula
                                    ? `Versión activa: v${activeFormula.version}`
                                    : 'Sin fórmula activa'}
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <Link
                                href={
                                    formulasCreate({ product_id: product.id })
                                        .url
                                }
                            >
                                Nueva Fórmula
                            </Link>
                        </Button>
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Versión
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Notas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Creada por
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {(product.formulas ?? []).map((formula) => (
                                <tr
                                    key={formula.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">
                                        v{formula.version}
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={
                                                formula.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {formula.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.notes ?? '-'}
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.created_by?.name ?? '-'}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        {formula.created_at}
                                    </td>
                                    <td className="p-4 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    formulasShow({
                                                        formula: formula.id,
                                                    }).url
                                                }
                                            >
                                                Ver
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {(product.formulas ?? []).length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay fórmulas registradas. Crea la
                                        primera usando el botón "Nueva Fórmula".
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
