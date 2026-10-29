import { Form, Head } from '@inertiajs/react';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    return (
        <>
            <Head title="Restablecer contraseña — Pintech" />

            <Form
                noValidate
                action={update.url()}
                method="post"
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <FormField
                            id="email"
                            label="Correo electrónico"
                            error={errors.email}
                        >
                            <Input
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                            />
                        </FormField>

                        <FormField
                            id="password"
                            label="Nueva contraseña"
                            error={errors.password}
                        >
                            <PasswordInput
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                placeholder="Nueva contraseña"
                            />
                        </FormField>

                        <FormField
                            id="password_confirmation"
                            label="Confirmar contraseña"
                            error={errors.password_confirmation}
                        >
                            <PasswordInput
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Confirmar contraseña"
                            />
                        </FormField>

                        <FormActions
                            submitLabel="Restablecer contraseña"
                            processing={processing}
                            fullWidth
                        />
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Restablecer contraseña',
    description: 'Ingresa tu nueva contraseña para continuar',
};
