/**
 * Auth Card Layout
 * Layout de autenticación con tarjeta centrada
 * Usa el logo oficial Pintech 2026 - ver /docs/LOGOS.md
 */
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="inline-flex items-center justify-center self-center"
                >
                    <img
                        src="/images/logo-pintech.svg?v=1.1"
                        alt="Pintech logo"
                        className="h-16 w-auto object-contain"
                    />
                </Link>
                <div className="flex flex-col gap-6">
                    <Card className="rounded-xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-2xl font-semibold tracking-tight">
                                {title}
                            </CardTitle>
                            <CardDescription className="text-sm leading-relaxed">
                                {description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
