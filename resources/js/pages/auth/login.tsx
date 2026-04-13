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
                        {/* Overlay azul (más ligero para que se vea la foto) */}
                        <div className="absolute inset-0 bg-linear-to-br from-[#0d2a6e]/55 via-[#0d2a6e]/45 to-[#0a2060]/50" />

                        {/* Badge superior */}
                        <div className="relative z-10">
                            <span className="text-primary-foreground/75 inline-flex items-center gap-2 rounded-full border border-primary-foreground/20 px-3 py-1 text-xs font-semibold tracking-widest uppercase">
                                <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                Sistema de Planta
                            </span>
                        </div>

                        {/* Frase central */}
                        <div className="relative z-10 space-y-4">
                            <h1 className="text-primary-foreground text-3xl leading-snug font-bold">
                                Precisión en cada gota.
                                <br />
                                Control en cada lote.
                            </h1>
                            <p className="text-primary-foreground/70 max-w-xs text-sm leading-relaxed">
                                Accede al ecosistema PINTECH para monitoreo en
                                tiempo real de inventarios, formulaciones y
                                producción de pinturas.
                            </p>
                        </div>

                        {/* Stats inferiores */}
                        <div className="relative z-10 flex gap-8">
                            <div>
                                <p className="text-2xl font-bold text-emerald-400">
                                    99.9%
                                </p>
                                <p className="text-primary-foreground/60 text-xs tracking-widest uppercase">
                                    Disponibilidad
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* ── Panel derecho ── */}
                    <div className="flex w-full flex-col justify-center bg-card px-10 py-12 md:w-1/2">
                        {/* Logo y título */}
                        <div className="mb-8">
                            <div className="mb-6 flex items-center gap-3">
                                <img
                                    src="/images/logo-pintech.png"
                                    alt="Pintech logo"
                                    className="h-9 w-auto shrink-0 object-contain"
                                    draggable={false}
                                    onError={(e) => {
                                        (
                                            e.target as HTMLImageElement
                                        ).style.display = 'none';
                                    }}
                                />
                                <div className="grid leading-tight">
                                    <span className="text-[11px] tracking-[0.22em] text-muted-foreground uppercase">
                                        Pintech OS
                                    </span>
                                    <span className="text-sm font-semibold text-foreground">
                                        Industrial Control
                                    </span>
                                </div>
                            </div>
                            <h2 className="text-xl font-semibold text-foreground">
                                Portal de Operaciones
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Ingresa a tu cuenta autorizada de planta.
                            </p>
                        </div>

                        {/* Mensaje de estado */}
                        {status && (
                            <div className="text-primary mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-center text-sm font-medium">
                                {status}
                            </div>
                        )}

                        {/* Formulario */}
                        <Form
                            action={store.url()}
                            method="post"
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    {/* Email */}
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="email"
                                            className="text-muted-foreground text-xs font-semibold tracking-widest uppercase"
                                        >
                                            Correo electrónico
                                        </Label>
                                        <div className="relative">
                                            <span className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2">
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
                                                className="rounded-lg border-input bg-background pl-9 text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/30"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    {/* Contraseña */}
                                    <div className="grid gap-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="password"
                                                className="text-muted-foreground text-xs font-semibold tracking-widest uppercase"
                                            >
                                                Contraseña
                                            </Label>
                                            {canResetPassword && (
                                                <a
                                                    href={request.url()}
                                                    className="text-primary text-xs hover:underline"
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
                                            className="rounded-lg border-input bg-background text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:ring-ring/30"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    {/* Recordarme */}
                                    <div className="flex cursor-pointer items-center gap-2">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                            className="cursor-pointer border-input data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-muted-foreground cursor-pointer text-sm"
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
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 mt-1 flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg py-2.5 font-semibold transition-all duration-200"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <>
                                                Iniciar sesión
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

                        {/* Footer */}
                        <div className="text-muted-foreground mt-8 flex items-center justify-between text-xs">
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
