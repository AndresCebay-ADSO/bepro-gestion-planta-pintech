import { Form, Head } from '@inertiajs/react';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contraseña" />

            <Form
                noValidate
                action={store.url()}
                method="post"
                resetOnSuccess={['password']}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <FormField
                            id="password"
                            label="Contraseña"
                            error={errors.password}
                        >
                            <PasswordInput
                                name="password"
                                placeholder="Contraseña"
                                autoComplete="current-password"
                                autoFocus
                            />
                        </FormField>

                        <FormActions
                            submitLabel="Confirmar contraseña"
                            processing={processing}
                            fullWidth
                        />
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirma tu contraseña',
    description:
        'Esta es un área segura de la aplicación. Por favor, confirma tu contraseña antes de continuar.',
};
