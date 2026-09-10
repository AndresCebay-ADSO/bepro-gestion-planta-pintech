import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import UserIdentityFields from '@/components/users/user-identity-fields';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage().props;
    const user = auth.user;

    const {
        data,
        setData,
        patch,
        post,
        processing,
        recentlySuccessful,
        errors,
    } = useForm({
        name: user?.name ?? '',
        email: user?.email ? user.email.toLowerCase() : '',
        job_title: user?.job_title ?? '',
        phone: user?.phone ?? '',
        signature: null as File | null,
        remove_signature: false,
    });

    if (!user) {
        return null;
    }

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const hasFile = data.signature instanceof File;
        const submitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                setData('signature', null);
                setData('remove_signature', false);
            },
        };

        if (hasFile) {
            post(ProfileController.update.url(), {
                ...submitOptions,
                forceFormData: true,
            });
        } else {
            patch(ProfileController.update.url(), submitOptions);
        }
    };

    const currentSignatureUrl = user.signature_url;

    return (
        <>
            <Head title="Configuración de perfil" />

            <h1 className="sr-only">Configuración de perfil</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Información del perfil"
                    description="Actualiza tu nombre, correo electrónico y cargo"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <UserIdentityFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        currentSignatureUrl={currentSignatureUrl}
                        disabled={processing}
                    />

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div>
                            <p className="-mt-4 text-sm text-muted-foreground">
                                Tu correo electrónico no está verificado.{' '}
                                <Link
                                    href={send()}
                                    as="button"
                                    className="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                >
                                    Haz clic aquí para reenviar el correo de
                                    verificación.
                                </Link>
                            </p>

                            {status === 'verification-link-sent' && (
                                <div className="mt-2 text-sm font-medium text-primary">
                                    Un nuevo enlace de verificación ha sido
                                    enviado a tu correo electrónico.
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={processing}
                            data-test="update-profile-button"
                        >
                            Guardar
                        </Button>

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">
                                Guardado
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Configuración de perfil',
            href: edit(),
        },
    ],
};

