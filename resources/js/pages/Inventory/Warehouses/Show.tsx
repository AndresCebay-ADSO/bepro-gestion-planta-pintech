import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';

type UserItem = {
    id: number;
    name: string;
    email: string;
    pivot?: {
        is_default?: boolean;
    };
};

type FinishedInventoryItem = {
    id: number;
    quantity: string;
    product: {
        id: number;
        code: string;
        name: string;
    } | null;
    product_variant: {
        id: number;
        code: string | null;
        name: string;
        presentation_label: string | null;
        presentation_value: number | null;
    } | null;
};

type Props = {
    warehouse: {
        id: number;
        name: string;
        city: string;
        address: string | null;
        type: 'factory' | 'storage';
        is_active: boolean;
        users: UserItem[];
        finished_inventories: FinishedInventoryItem[];
    };
    can: {
        update: boolean;
        delete: boolean;
        assignUsers: boolean;
    };
};

export default function WarehousesShow({ warehouse, can }: Props) {
    const handleDelete = () => {
        if (!window.confirm('¿Estás seguro de eliminar esta bodega?')) {
            return;
        }

        router.delete(route('warehouses.destroy', warehouse.id));
    };

    return (
        <>
            <Head title={`Bodega ${warehouse.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {warehouse.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Detalle de bodega, usuarios e inventario terminado.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('warehouses.index')}>Volver</Link>
                        </Button>
                        {can.assignUsers && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={route(
                                        'warehouses.assign-users.form',
                                        warehouse.id,
                                    )}
                                >
                                    Asignar usuarios
                                </Link>
                            </Button>
                        )}
                        {can.update && (
                            <Button asChild>
                                <Link
                                    href={route(
                                        'warehouses.edit',
                                        warehouse.id,
                                    )}
                                >
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

                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-2">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Ciudad
                        </p>
                        <p className="text-sm text-foreground">
                            {warehouse.city}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Estado
                        </p>
                        <p className="text-sm text-foreground">
                            {warehouse.is_active ? 'Activa' : 'Inactiva'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Tipo
                        </p>
                        <p className="font-medium">
                            {warehouse.type === 'factory'
                                ? 'Fábrica (Producción)'
                                : 'Bodega (Almacenamiento / Venta)'}
                        </p>
                    </div>
                    <div className="md:col-span-2">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Dirección
                        </p>
                        <p className="text-sm text-foreground">
                            {warehouse.address ?? '-'}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="overflow-x-auto rounded-lg border border-border bg-card">
                        <div className="border-b border-border px-4 py-3">
                            <h2 className="font-medium text-foreground">
                                Usuarios asignados
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/40">
                                <tr>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Nombre
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Correo
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Predeterminada
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {warehouse.users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-border/60 last:border-0"
                                    >
                                        <td className="p-3 text-foreground">
                                            {user.name}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {user.pivot?.is_default
                                                ? 'Sí'
                                                : 'No'}
                                        </td>
                                    </tr>
                                ))}
                                {warehouse.users.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="p-8 text-center text-sm text-muted-foreground"
                                        >
                                            No hay usuarios asignados.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-border bg-card">
                        <div className="border-b border-border px-4 py-3">
                            <h2 className="font-medium text-foreground">
                                Stock de producto terminado
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/40">
                                <tr>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Código
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Producto
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Variante
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Cantidad
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {warehouse.finished_inventories.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b border-border/60 last:border-0"
                                    >
                                        <td className="p-3 text-foreground">
                                            {item.product?.code ?? '-'}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {item.product?.name ?? '-'}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {item.product_variant ? (
                                                <>
                                                    {item.product_variant.name}
                                                    {item.product_variant
                                                        .presentation_label && (
                                                        <span className="ml-1 text-xs">
                                                            (
                                                            {
                                                                item
                                                                    .product_variant
                                                                    .presentation_label
                                                            }
                                                            )
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {item.quantity}
                                        </td>
                                    </tr>
                                ))}
                                {warehouse.finished_inventories.length ===
                                    0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-8 text-center text-sm text-muted-foreground"
                                        >
                                            No hay inventario terminado en esta
                                            bodega.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
