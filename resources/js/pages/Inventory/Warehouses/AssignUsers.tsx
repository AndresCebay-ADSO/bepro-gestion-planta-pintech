import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

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

        form.post(route('warehouses.assign-users', warehouse.id));
    };

    return (
        <>
            <Head title={`Asignar usuarios - ${warehouse.name}`} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Asignar Usuarios
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Bodega: {warehouse.name} ({warehouse.city})
                    </p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="space-y-4 rounded-lg border border-border bg-card p-6"
                >
                    <div className="overflow-x-auto">
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
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
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
                                                    <Label className="text-xs text-muted-foreground">
                                                        Predeterminada
                                                    </Label>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {form.errors.users && (
                        <p className="text-sm text-destructive">
                            {form.errors.users}
                        </p>
                    )}

                    <div className="flex flex-col gap-2 pt-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando...'
                                : 'Guardar asignaciones'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={route('warehouses.show', warehouse.id)}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
