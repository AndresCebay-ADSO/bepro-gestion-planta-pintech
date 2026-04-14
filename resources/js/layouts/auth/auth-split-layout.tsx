/**
 * Auth Split Layout
 * Layout de autenticación con panel dividido
 * Usa el logo oficial Pintech 2026 - ver /docs/LOGOS.md
 */
import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col border-r border-border bg-card p-10 text-card-foreground lg:flex">
                <div className="absolute inset-0 bg-linear-to-br from-slate-900 via-slate-900 to-slate-800" />
                <Link
                    href={home()}
                    className="relative z-20 inline-flex w-fit items-center justify-center"
                >
                    <img
                        src="/images/logo-pintech.png"
                        alt="Pintech logo"
                        className="h-16 w-auto object-contain"
                    />
                </Link>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="inline-flex items-center justify-center lg:hidden"
                    >
                        <img
                            src="/images/logo-pintech.png"
                            alt="Pintech logo"
                            className="h-16 w-auto object-contain"
                        />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            {title}
                        </h1>
                        <p className="text-sm leading-relaxed text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
