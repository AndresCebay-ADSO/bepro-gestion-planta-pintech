import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar contraseña — Pintech" />

            <div className="flex min-h-svh w-full items-center justify-center bg-slate-100 p-4 text-slate-900 dark:bg-slate-100">
                <div className="flex w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xl shadow-slate-200/60 dark:border-slate-200/90 dark:bg-white dark:shadow-slate-300/50">
                    {/* ── Panel izquierdo ── */}
                    <div
                        className="relative hidden w-1/2 flex-col justify-between overflow-hidden p-10 md:flex"
                        style={{
                            backgroundImage: 'url(/images/side-login.jpg)',
                            backgroundSize: 'cover',
                            backgroundPosition: 'center',
                        }}
                    >
                        <div className="absolute inset-0 bg-gradient-to-br from-[#0d2a6e]/55 via-[#0d2a6e]/45 to-[#0a2060]/50" />

                        <div className="relative z-10">
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1 text-xs font-semibold tracking-widest text-white/60 uppercase">
                                <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                Sistema de Planta
                            </span>
                        </div>

                        <div className="relative z-10 space-y-4">
                            <h1 className="text-3xl leading-snug font-bold text-white">
                                Recupera el acceso
                                <br />a tu cuenta.
                            </h1>
                            <p className="max-w-xs text-sm leading-relaxed text-white/60">
                                Indica el correo con el que registraste tu
                                usuario y te enviaremos un enlace para
                                restablecer tu contraseña de forma segura.
                            </p>
                        </div>

                        <div className="relative z-10 flex gap-8">
                            <div>
                                <p className="text-2xl font-bold text-emerald-400">
                                    Seguro
                                </p>
                                <p className="text-xs tracking-widest text-white/50 uppercase">
                                    Enlace de un solo uso
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* ── Panel derecho ── */}
                    <div className="flex w-full flex-col justify-center bg-white px-10 py-12 md:w-1/2 dark:bg-white">
                        <div className="mb-8">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0d2a6e]">
                                    <img
                                        src="/images/logo-pintech.png"
                                        alt="Pintech logo"
                                        className="h-5 w-5 object-contain"
                                        onError={(e) => {
                                            (
                                                e.target as HTMLImageElement
                                            ).style.display = 'none';
                                        }}
                                    />
                                </div>
                                <span className="text-lg font-bold tracking-wide text-[#0d2a6e] uppercase">
                                    Pintech
                                </span>
                            </div>
                            <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-900">
                                ¿Olvidaste tu contraseña?
                            </h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-500">
                                Escribe tu correo y te enviaremos instrucciones
                                para crear una nueva.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-center text-sm font-medium text-emerald-600">
                                {status}
                            </div>
                        )}

                        <Form {...email.form()} className="flex flex-col gap-5">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="email"
                                            className="text-xs font-semibold tracking-widest text-gray-500 uppercase dark:text-gray-500"
                                        >
                                            Correo electrónico
                                        </Label>
                                        <div className="relative">
                                            <span className="absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
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
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="gerente.planta@pintech.com"
                                                className="rounded-lg border-slate-200 bg-white pl-9 text-sm text-slate-900 selection:bg-sky-200 selection:text-slate-900 placeholder:text-slate-400 focus:border-[#0d2a6e] focus:ring-[#0d2a6e]/20 dark:border-slate-200 dark:bg-white dark:text-slate-900 dark:selection:bg-sky-200 dark:selection:text-slate-900 dark:placeholder:text-slate-400"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <Button
                                        type="submit"
                                        tabIndex={2}
                                        disabled={processing}
                                        data-test="email-password-reset-link-button"
                                        className="mt-1 flex w-full items-center justify-center gap-2 rounded-lg bg-[#0d2a6e] py-2.5 font-semibold text-white transition-all duration-200 hover:bg-[#0a2060]"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <>
                                                Enviar enlace
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    className="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    strokeWidth="2.5"
                                                >
                                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                                </svg>
                                            </>
                                        )}
                                    </Button>
                                </>
                            )}
                        </Form>

                        <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-500">
                            <Link
                                href={login.url()}
                                className="font-medium text-[#0d2a6e] hover:underline"
                            >
                                Volver a iniciar sesión
                            </Link>
                        </p>

                        <div className="mt-8 flex items-center justify-between text-xs text-gray-500 dark:text-gray-500">
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
