/**
 * Auth Simple Layout
 * Layout de autenticación simple centrado
 * Usa el logo oficial Pintech 2026 - ver /docs/LOGOS.md
 */
import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="inline-flex items-center justify-center"
                        >
                            <img
                                src="/images/logo-pintech.svg?v=1.1"
                                alt="Pintech logo"
                                className="h-24 w-auto object-contain"
                            />
                        </Link>
                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {title}
                            </h1>
                            <p className="text-center text-sm leading-relaxed text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
