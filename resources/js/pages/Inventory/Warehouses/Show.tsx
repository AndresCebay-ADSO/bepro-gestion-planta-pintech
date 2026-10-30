import { Head, Link, router } from '@inertiajs/react';
import { ActiveBadge } from '@/components/active-badge';
import { DetailTable } from '@/components/detail-table';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/hooks/use-confirm';
import {
    destroy as warehousesDestroy,
    edit as warehousesEdit,
    index as warehousesIndex,
    show as warehousesShow,
} from '@/routes/warehouses';
import { form as assignUsersForm } from '@/routes/warehouses/assign-users';

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
    quantity: number;
    product: {
        id: number;
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
    const confirm = useConfirm();

    const handleDelete = async () => {
        if (
            !(await confirm({
                title: `¿Eliminar la bodega «${warehouse.name}»?`,
                confirmLabel: 'Eliminar',
                destructive: true,
            }))
        ) {
            return;
        }

        router.delete(warehousesDestroy(warehouse.id).url);
    };

    return (
        <>
            <Head title={warehouse.name} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={warehouse.name}
                    description="Detalle de bodega, usuarios e inventario terminado."
                    actions={
                        <>
                            {can.assignUsers && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={assignUsersForm(warehouse.id).url}
                                    >
                                        Asignar usuarios
                                    </Link>
                                </Button>
                            )}
                            {can.update && (
                                <Button asChild>
                                    <Link
                                        href={warehousesEdit(warehouse.id).url}
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
                        </>
                    }
                />

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
                        <div className="mt-1">
                            <ActiveBadge
                                isActive={warehouse.is_active}
                                feminine
                            />
                        </div>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Tipo
                        </p>
                        <div className="mt-1">
                            <StatusBadge
                                enum="WarehouseType"
                                value={warehouse.type}
                            />
                        </div>
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
                    {/* min-w-0: sin él, la columna de la grilla crece con la tabla y la tabla no se desplaza. */}
                    <section className="min-w-0 space-y-4">
                        <h2 className="text-lg font-semibold">
                            Usuarios asignados
                        </h2>
                        <DetailTable
                            rows={warehouse.users}
                            rowKey={(user) => user.id}
                            caption="Usuarios asignados"
                            columns={[
                                {
                                    key: 'name',
                                    header: 'Nombre',
                                    cell: (user) => user.name,
                                },
                                {
                                    key: 'email',
                                    header: 'Correo',
                                    cell: (user) => user.email,
                                    className: 'text-muted-foreground',
                                },
                                {
                                    key: 'default',
                                    header: 'Predeterminada',
                                    cell: (user) =>
                                        user.pivot?.is_default ? 'Sí' : 'No',
                                    align: 'center',
                                    className: 'text-muted-foreground',
                                },
                            ]}
                            empty="No hay usuarios asignados."
                        />
                    </section>

                    <section className="min-w-0 space-y-4">
                        <h2 className="text-lg font-semibold">
                            Stock de producto terminado
                        </h2>
                        <DetailTable
                            rows={warehouse.finished_inventories}
                            rowKey={(item) => item.id}
                            caption="Stock de producto terminado"
                            columns={[
                                {
                                    key: 'product',
                                    header: 'Producto',
                                    cell: (item) => item.product?.name ?? null,
                                },
                                {
                                    key: 'variant',
                                    header: 'Variante',
                                    cell: (item) =>
                                        item.product_variant ? (
                                            <>
                                                {item.product_variant.name}
                                                {item.product_variant
                                                    .presentation_label && (
                                                    <span className="ml-1 text-xs">
                                                        (
                                                        {
                                                            item.product_variant
                                                                .presentation_label
                                                        }
                                                        )
                                                    </span>
                                                )}
                                            </>
                                        ) : null,
                                    className: 'text-muted-foreground',
                                },
                                {
                                    key: 'quantity',
                                    header: 'Cantidad',
                                    cell: (item) => (
                                        <FormattedNumber
                                            value={item.quantity}
                                            maxDecimals={2}
                                        />
                                    ),
                                    align: 'right',
                                },
                            ]}
                            empty="No hay inventario terminado en esta bodega."
                        />
                    </section>
                </div>
            </div>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
WarehousesShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Bodegas', href: warehousesIndex() },
        {
            title: props.warehouse.name,
            href: warehousesShow(props.warehouse.id),
        },
    ],
});
