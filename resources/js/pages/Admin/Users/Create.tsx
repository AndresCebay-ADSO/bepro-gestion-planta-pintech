import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import UserController from '@/actions/App/Http/Controllers/UserController';
import { FormActions } from '@/components/form-actions';
import { FormCheckbox, FormField } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import PasswordInput from '@/components/password-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UserIdentityFields from '@/components/users/user-identity-fields';
import type { RoleOption } from '@/types';

interface Props {
    roles: RoleOption[];
}

const UsersCreate = ({ roles }: Props) => {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        job_title: '',
        phone: '',
        signature: null as File | null,
        remove_signature: false,
        password: '',
        password_confirmation: '',
        // Sin rol preseleccionado: quien crea el usuario lo elige siempre.
        role: '',
        is_active: true,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(UserController.store.url(), {
            forceFormData: true,
            onSuccess: () =>
                reset('password', 'password_confirmation', 'signature'),
        });
    };

    return (
        <>
            <Head title="Nuevo usuario" />
            <FormPage width="narrow">
                <PageHeader
                    title="Nuevo usuario"
                    description="Agrega un nuevo usuario al sistema con sus credenciales y permisos."
                />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6 rounded-lg border border-border bg-card p-6">
                        <UserIdentityFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            disabled={processing}
                        />

                        <div className="space-y-6 border-t border-border pt-6">
                            <FormField
                                id="role"
                                label="Rol"
                                required
                                error={errors.role}
                            >
                                {(control) => (
                                    <Select
                                        value={data.role}
                                        onValueChange={(val) =>
                                            setData('role', val)
                                        }
                                        disabled={processing}
                                    >
                                        <SelectTrigger {...control}>
                                            <SelectValue placeholder="Seleccionar rol..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.id}
                                                    value={role.name}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>

                            <FormCheckbox
                                id="is_active"
                                label="Usuario activo"
                                error={errors.is_active}
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                                disabled={processing}
                            />
                        </div>

                        <div className="grid gap-6 border-t border-border pt-6 md:grid-cols-2">
                            <FormField
                                id="password"
                                label="Contraseña"
                                required
                                error={errors.password}
                            >
                                <PasswordInput
                                    name="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder="Mínimo 8 caracteres"
                                    disabled={processing}
                                />
                            </FormField>

                            <FormField
                                id="password_confirmation"
                                label="Confirmar contraseña"
                                required
                                error={errors.password_confirmation}
                            >
                                <PasswordInput
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Repite la contraseña"
                                    disabled={processing}
                                />
                            </FormField>
                        </div>
                    </div>

                    <FormActions
                        submitLabel="Crear usuario"
                        processing={processing}
                        cancelHref={UserController.index.url()}
                    />
                </form>
            </FormPage>
        </>
    );
};

// Las migas son la única forma de volver al listado (DESIGN.md §10).
UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: UserController.index() },
        { title: 'Nuevo usuario', href: UserController.create() },
    ],
};

export default UsersCreate;
