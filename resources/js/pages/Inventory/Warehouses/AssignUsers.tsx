import { Head, useForm } from '@inertiajs/react';
import { FormActions } from '@/components/form-actions';
import { FormCheckbox, FormError } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { Checkbox } from '@/components/ui/checkbox';
import {
    assignUsers,
    index as warehousesIndex,
    show as warehousesShow,
} from '@/routes/warehouses';
import { form as assignUsersForm } from '@/routes/warehouses/assign-users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    assigned: boolean;
    is_default: boolean;
};

type Props = {
    warehouse: {
        id: number;
        name: string;
        city: string;
    };
    users: UserRow[];
};

type UserAssignment = {
    user_id: number;
    assigned: boolean;
    is_default: boolean;
};

type AssignUsersForm = {
    users: UserAssignment[];
};

export default function WarehouseAssignUsers({ warehouse, users }: Props) {
    const form = useForm<AssignUsersForm>({
        users: users.map((user) => ({
            user_id: user.id,
            assigned: user.assigned,
            is_default: user.assigned ? user.is_default : false,
        })),
    });

    const updateRow = (index: number, patch: Partial<UserAssignment>) => {
        form.setData(
            'users',
            form.data.users.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        );
    };

    const submit = () => {
        form.transform((data) => ({
            users: data.users
                .filter((item) => item.assigned)
                .map((item) => ({
                    user_id: item.user_id,
                    is_default: item.is_default,
                })),
        }));

        form.post(assignUsers(warehouse.id).url);
    };

    return (
        <>
            <Head title={`Asignar usuarios: ${warehouse.name}`} />

            <FormPage width="narrow">
                <PageHeader
                    title={`Asignar usuarios: ${warehouse.name}`}
                    description={`Bodega: ${warehouse.name} (${warehouse.city})`}
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="space-y-6"
                >
                    <div className="overflow-x-auto rounded-lg border border-border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/40">
                                <tr>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Asignar
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Usuario
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Correo
                                    </th>
                                    <th className="p-3 text-left font-medium text-foreground">
                                        Bodega por defecto
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user, index) => {
                                    const row = form.data.users[index];

                                    return (
                                        <tr
                                            key={user.id}
                                            className="border-b border-border/60 last:border-0"
                                        >
                                            <td className="p-3">
                                                <Checkbox
                                                    checked={row.assigned}
                                                    aria-label={`Asignar a ${user.name}`}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        updateRow(index, {
                                                            assigned:
                                                                checked ===
                                                                true,
                                                            is_default:
                                                                checked === true
                                                                    ? row.is_default
                                                                    : false,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="p-3 text-foreground">
                                                {user.name}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {user.email}
                                            </td>
                                            <td className="p-3">
                                                <FormCheckbox
                                                    id={`is_default-${user.id}`}
                                                    label={
                                                        <span className="text-xs font-normal text-muted-foreground">
                                                            Predeterminada
                                                            <span className="sr-only">
                                                                {' '}
                                                                para {user.name}
                                                            </span>
                                                        </span>
                                                    }
                                                    checked={row.is_default}
                                                    disabled={!row.assigned}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        updateRow(index, {
                                                            is_default:
                                                                checked ===
                                                                true,
                                                        })
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <FormError id="users" message={form.errors.users} />

                    <FormActions
                        submitLabel="Guardar asignaciones"
                        processing={form.processing}
                        cancelHref={warehousesShow(warehouse.id).url}
                    />
                </form>
            </FormPage>
        </>
    );
}

WarehouseAssignUsers.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Bodegas', href: warehousesIndex() },
        {
            title: props.warehouse.name,
            href: warehousesShow(props.warehouse.id),
        },
        {
            title: 'Asignar usuarios',
            href: assignUsersForm(props.warehouse.id),
        },
    ],
});
