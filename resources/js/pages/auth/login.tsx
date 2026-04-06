import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Iniciar sesión — Pintech" />

            <div className="flex min-h-svh w-full items-center justify-center bg-slate-100 dark:bg-slate-100 p-4 text-slate-900">
                <div className="flex w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xl shadow-slate-200/60 dark:border-slate-200/90 dark:bg-white dark:shadow-slate-300/50">

                    {/* ── Panel izquierdo ── */}
                    <div
                        className="hidden md:flex flex-col justify-between w-1/2 p-10 relative overflow-hidden"
                        style={{
                            backgroundImage: 'url(/images/side-login.jpg)',
                            backgroundSize: 'cover',
                            backgroundPosition: 'center',
                        }}
                    >
                        {/* Overlay azul (más ligero para que se vea la foto) */}
                        <div className="absolute inset-0 bg-gradient-to-br from-[#0d2a6e]/55 via-[#0d2a6e]/45 to-[#0a2060]/50" />

                        {/* Badge superior */}
                        <div className="relative z-10">
                            <span className="inline-flex items-center gap-2 text-xs font-semibold tracking-widest uppercase text-white/60 border border-white/20 rounded-full px-3 py-1">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block" />
                                Sistema de Planta
                            </span>
                        </div>

                        {/* Frase central */}
                        <div className="relative z-10 space-y-4">
                            <h1 className="text-3xl font-bold text-white leading-snug">
                                Precisión en cada gota.<br />
                                Control en cada lote.
                            </h1>
                            <p className="text-sm text-white/60 leading-relaxed max-w-xs">
                                Accede al ecosistema PINTECH para monitoreo en tiempo real de inventarios, formulaciones y producción de pinturas.
                            </p>
                        </div>

                        {/* Stats inferiores */}
                        <div className="relative z-10 flex gap-8">
                            <div>
                                <p className="text-2xl font-bold text-emerald-400">99.9%</p>
                                <p className="text-xs text-white/50 uppercase tracking-widest">Disponibilidad</p>
                            </div>
                        </div>
                    </div>

                    {/* ── Panel derecho ── */}
                    <div className="flex w-full flex-col justify-center bg-white px-10 py-12 md:w-1/2 dark:bg-white">

                        {/* Logo y título */}
                        <div className="mb-8">
                            <div className="flex items-center gap-3 mb-6">
                                <div className="w-9 h-9 rounded-lg bg-[#0d2a6e] flex items-center justify-center">
                                    <img
                                        src="/images/logo-pintech.png"
                                        alt="Pintech logo"
                                        className="w-5 h-5 object-contain"
                                        onError={(e) => {
                                            (e.target as HTMLImageElement).style.display = 'none';
                                        }}
                                    />
                                </div>
                                <span className="text-lg font-bold tracking-wide text-[#0d2a6e] uppercase">
                                    Pintech
                                </span>
                            </div>
                            <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-900">
                                Portal de Operaciones
                            </h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-500">
                                Ingresa a tu cuenta autorizada de planta.
                            </p>
                        </div>

                        {/* Mensaje de estado */}
                        {status && (
                            <div className="mb-4 text-center text-sm font-medium text-emerald-600 bg-emerald-50 rounded-lg px-4 py-2">
                                {status}
                            </div>
                        )}

                        {/* Formulario */}
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    {/* Email */}
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="email"
                                            className="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-500"
                                        >
                                            Correo electrónico
                                        </Label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
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
                                                className="rounded-lg border-slate-200 bg-white pl-9 text-sm text-slate-900 placeholder:text-slate-400 selection:bg-sky-200 selection:text-slate-900 focus:border-[#0d2a6e] focus:ring-[#0d2a6e]/20 dark:border-slate-200 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-400 dark:selection:bg-sky-200 dark:selection:text-slate-900"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    {/* Contraseña */}
                                    <div className="grid gap-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="password"
                                                className="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-500"
                                            >
                                                Contraseña
                                            </Label>
                                            {canResetPassword && (
                                                <a
                                                    href={request.url()}
                                                    className="text-xs text-[#0d2a6e] hover:underline"
                                                    tabIndex={5}
                                                >
                                                    ¿Olvidaste tu contraseña?
                                                </a>
                                            )}
                                        </div>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="••••••••••••"
                                            className="rounded-lg border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 selection:bg-sky-200 selection:text-slate-900 focus:border-[#0d2a6e] focus:ring-[#0d2a6e]/20 dark:border-slate-200 dark:bg-white dark:text-slate-900 dark:placeholder:text-slate-400 dark:selection:bg-sky-200 dark:selection:text-slate-900"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    {/* Recordarme */}
                                    <div className="flex items-center gap-2 cursor-pointer">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                            className="border-gray-300 data-[state=checked]:bg-white data-[state=checked]:border-[#0d2a6e] cursor-pointer"
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="cursor-pointer text-sm text-gray-500 dark:text-gray-600"
                                        >
                                            Recordar sesión
                                        </Label>
                                    </div>

                                    {/* Botón */}
                                    <Button
                                        type="submit"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                        className="w-full bg-[#0d2a6e] hover:bg-[#0a2060] text-white font-semibold rounded-lg py-2.5 flex items-center justify-center gap-2 transition-all duration-200 mt-1 cursor-pointer"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <>
                                                Iniciar sesión
                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                                </svg>
                                            </>
                                        )}
                                    </Button>
                                </>
                            )}
                        </Form>

                        {/* Footer */}
                        <div className="mt-8 flex items-center justify-between text-xs text-gray-500 dark:text-gray-500">
                            <span className="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
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