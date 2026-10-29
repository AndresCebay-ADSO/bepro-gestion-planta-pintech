import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import UserController from '@/actions/App/Http/Controllers/UserController';
import { FormActions } from '@/components/form-actions';
import { FormCheckbox, FormField } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UserIdentityFields from '@/components/users/user-identity-fields';
import type { Role, RoleOption, User } from '@/types';

interface Props {
    user: User & { roles: Role[] };
    roles: RoleOption[];
}

const UsersEdit = ({ user, roles }: Props) => {
    const { data, setData, post, put, transform, processing, errors } = useForm(
        {
            name: user.name,
            email: user.email ? user.email.toLowerCase() : '',
            job_title: user.job_title ?? '',
            phone: user.phone ?? '',
            signature: null as File | null,
            remove_signature: false,
            role: user.roles[0]?.name ?? '',
            is_active: user.is_active ?? true,
        },
    );

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (data.signature instanceof File) {
            transform((currentData) => ({
                ...currentData,
                _method: 'put',
            }));
            post(UserController.update.url(user.id), {
                forceFormData: true,
            });
        } else {
            transform((currentData) => currentData);
            put(UserController.update.url(user.id));
        }
    };

    return (
        <>
            <Head title="Editar usuario" />
            <FormPage width="narrow">
                <PageHeader
                    title="Editar usuario"
                    description="Actualiza la información, rol y permisos del usuario."
                />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6 rounded-lg border border-border bg-card p-6">
                        <UserIdentityFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            currentSignatureUrl={user.signature_url}
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
                    </div>

                    <div className="rounded-lg border border-primary/30 bg-primary/10 p-4">
                        <p className="text-sm text-primary">
                            💡 Para cambiar la contraseña, el usuario debe usar
                            la opción "Olvidé mi contraseña" en el login.
                        </p>
                    </div>

                    <FormActions
                        submitLabel="Guardar cambios"
                        processing={processing}
                        cancelHref={UserController.index.url()}
                    />
                </form>
            </FormPage>
        </>
    );
};

UsersEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Usuarios', href: UserController.index() },
        { title: props.user.name, href: UserController.edit(props.user.id) },
    ],
});

export default UsersEdit;
