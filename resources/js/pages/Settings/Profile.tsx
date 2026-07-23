import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    const signatureInputRef = useRef<HTMLInputElement>(null);
    const [signaturePreview, setSignaturePreview] = useState<string | null>(
        null,
    );
    const [signatureReadError, setSignatureReadError] = useState<string | null>(
        null,
    );

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
        email: user?.email ?? '',
        job_title: (user as any)?.job_title ?? '',
        phone: (user as any)?.phone ?? '',
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
                setSignaturePreview(null);

                if (signatureInputRef.current) {
                    signatureInputRef.current.value = '';
                }

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

    const handleSignatureChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            setData('signature', file);
            setData('remove_signature', false);
            setSignatureReadError(null);
            const reader = new FileReader();
            reader.onload = (event) => {
                setSignaturePreview(event.target?.result as string);
            };
            reader.onerror = () => {
                setSignatureReadError(
                    'No se pudo leer el archivo seleccionado.',
                );
            };
            reader.readAsDataURL(file);
        }
    };

    const handleRemoveSignature = () => {
        setData('signature', null);
        setData('remove_signature', true);
        setSignaturePreview(null);
        setSignatureReadError(null);

        if (signatureInputRef.current) {
            signatureInputRef.current.value = '';
        }
    };

    const currentSignatureUrl = (user as any).signature_url;

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
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>

                        <Input
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                            placeholder="Nombre completo"
                        />

                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo electrónico</Label>

                        <Input
                            id="email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                            placeholder="Correo electrónico"
                        />

                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="job_title">Cargo</Label>

                        <Input
                            id="job_title"
                            className="mt-1 block w-full"
                            value={data.job_title}
                            onChange={(e) =>
                                setData('job_title', e.target.value)
                            }
                            placeholder="Ej: Gerente de Producción"
                        />

                        <InputError
                            className="mt-2"
                            message={errors.job_title}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">Teléfono</Label>

                        <Input
                            id="phone"
                            type="tel"
                            className="mt-1 block w-full"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="Ej: 3001234567"
                        />

                        <InputError className="mt-2" message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="signature">Firma digital</Label>

                        {(signaturePreview || currentSignatureUrl) && (
                            <div className="relative inline-block max-w-[200px] rounded-lg border border-border p-2">
                                <img
                                    src={
                                        signaturePreview ?? currentSignatureUrl
                                    }
                                    alt="Vista previa de firma"
                                    className="h-16 w-full object-contain"
                                />
                                <button
                                    type="button"
                                    onClick={handleRemoveSignature}
                                    className="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-destructive text-xs text-destructive-foreground hover:bg-destructive/90"
                                >
                                    ×
                                </button>
                            </div>
                        )}

                        <Input
                            ref={signatureInputRef}
                            id="signature"
                            type="file"
                            className="mt-1 block w-full"
                            accept="image/png,image/jpeg"
                            onChange={handleSignatureChange}
                        />

                        <p className="text-xs text-muted-foreground">
                            Formatos: PNG, JPG. Máximo 1 MB.
                        </p>

                        {signatureReadError && (
                            <p className="mt-1 text-xs text-destructive">
                                {signatureReadError}
                            </p>
                        )}

                        <InputError
                            className="mt-2"
                            message={errors.signature}
                        />
                    </div>

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
