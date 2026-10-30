import { Form, Head, Link } from '@inertiajs/react';
import { AuthFlashError } from '@/components/auth-flash-error';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar contraseña — Pintech" />

            <div className="flex min-h-svh w-full items-center justify-center bg-background p-4 text-foreground">
                <div className="flex w-full max-w-4xl overflow-hidden rounded-2xl border border-border bg-card shadow-xl shadow-slate-900/10 dark:shadow-slate-950/30">
                    {/* ── Panel izquierdo ── */}
                    <div
                        className="relative hidden w-1/2 flex-col justify-between overflow-hidden p-10 md:flex"
                        style={{
                            backgroundImage: 'url(/images/side-login.jpg)',
                            backgroundSize: 'cover',
                            backgroundPosition: 'center',
                        }}
                    >
                        <div className="absolute inset-0 bg-linear-to-br from-[#0d2a6e]/55 via-[#0d2a6e]/45 to-[#0a2060]/50" />

                        <div className="relative z-10">
                            <span className="inline-flex items-center gap-2 rounded-full border border-primary-foreground/20 px-3 py-1 text-xs font-semibold tracking-widest text-primary-foreground/75 uppercase">
                                <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                Sistema de Planta
                            </span>
                        </div>

                        <div className="relative z-10 space-y-4">
                            <h1 className="text-3xl leading-snug font-semibold text-primary-foreground">
                                Recupera el acceso
                                <br />a tu cuenta.
                            </h1>
                            <p className="max-w-xs text-sm leading-relaxed text-primary-foreground/70">
                                Indica el correo con el que registraste tu
                                usuario y te enviaremos un enlace para
                                restablecer tu contraseña de forma segura.
                            </p>
                        </div>

                        <div className="relative z-10 flex gap-8">
                            <div>
                                <p className="text-2xl font-semibold text-emerald-400">
                                    Seguro
                                </p>
                                <p className="text-xs tracking-widest text-primary-foreground/60 uppercase">
                                    Enlace de un solo uso
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* ── Panel derecho ── */}
                    <div className="flex w-full flex-col justify-center bg-card px-10 py-12 md:w-1/2">
                        <div className="mb-8">
                            <div className="mb-6 flex items-center gap-3">
                                <img
                                    src="/images/logo-pintech.png"
                                    alt="Logo de Pintech"
                                    className="h-9 w-auto shrink-0 object-contain"
                                    draggable={false}
                                    onError={(e) => {
                                        (
                                            e.target as HTMLImageElement
                                        ).style.display = 'none';
                                    }}
                                />
                                <div className="grid leading-tight">
                                    <span className="text-xs tracking-[0.22em] text-muted-foreground uppercase">
                                        Pintech OS
                                    </span>
                                    <span className="text-sm font-semibold text-foreground">
                                        Industrial Control
                                    </span>
                                </div>
                            </div>
                            <h2 className="text-xl font-semibold text-foreground">
                                ¿Olvidaste tu contraseña?
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Escribe tu correo y te enviaremos instrucciones
                                para crear una nueva.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-center text-sm font-medium text-primary">
                                {status}
                            </div>
                        )}

                        <AuthFlashError className="mb-4" />

                        <Form
                            noValidate
                            action={email.url()}
                            method="post"
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="email"
                                        label="Correo electrónico"
                                        error={errors.email}
                                    >
                                        {(control) => (
                                            <div className="relative">
                                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        className="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                    >
                                                        <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" />
                                                        <polyline points="22,6 12,13 2,6" />
                                                    </svg>
                                                </span>
                                                <Input
                                                    {...control}
                                                    type="email"
                                                    name="email"
                                                    autoFocus
                                                    autoComplete="email"
                                                    placeholder="gerente.planta@pintech.com"
                                                    className="rounded-lg border-input bg-background pl-9 text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/30"
                                                />
                                            </div>
                                        )}
                                    </FormField>

                                    <FormActions
                                        submitLabel="Enviar enlace"
                                        processing={processing}
                                        fullWidth
                                    />
                                </>
                            )}
                        </Form>

                        <p className="mt-6 text-center text-sm text-muted-foreground">
                            <Link
                                href={login.url()}
                                className="font-medium text-primary hover:underline"
                            >
                                Volver a iniciar sesión
                            </Link>
                        </p>

                        <div className="mt-8 flex items-center justify-between text-xs text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                >
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                                Terminal seguro
                            </span>
                            <span>Pintech Colombia S.A.S</span>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
