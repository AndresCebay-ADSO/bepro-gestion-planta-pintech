// Components
import { Form, Head } from '@inertiajs/react';
import { AuthStatus } from '@/components/auth-status';
import { FormActions } from '@/components/form-actions';
import TextLink from '@/components/text-link';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificación de correo electrónico" />

            {status === 'verification-link-sent' && (
                <AuthStatus>
                    Se ha enviado un nuevo enlace de verificación al correo
                    electrónico que proporcionaste durante el registro.
                </AuthStatus>
            )}

            <Form
                noValidate
                action={send.url()}
                method="post"
                className="space-y-6 text-center"
            >
                {({ processing }) => (
                    <>
                        <FormActions
                            submitLabel="Reenviar correo de verificación"
                            processing={processing}
                            fullWidth
                        />

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Cerrar sesión
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verifica tu correo electrónico',
    description:
        'Por favor, verifica tu dirección de correo electrónico haciendo clic en el enlace que te enviamos por correo.',
};
