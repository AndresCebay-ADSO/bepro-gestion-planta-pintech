import { createInertiaApp, router } from '@inertiajs/react';
import { ConfirmDialogProvider } from '@/components/confirm-dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/Settings/layout';
import { focusFirstError } from '@/lib/focus-first-error';

let appName = import.meta.env.VITE_APP_NAME || 'Pintech Colombia S.A.S';

if (typeof window !== 'undefined') {
    const el = document.getElementById('app');

    if (el && el.dataset.page) {
        try {
            const page = JSON.parse(el.dataset.page);

            if (page?.props?.name) {
                appName = page.props.name;
            }
        } catch {
            // Fallback silencioso
        }
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'ErrorPage':
                return null;
            // Login y recuperación tienen UI propia (split)
            case name === 'auth/login':
            case name === 'auth/forgot-password':
                return null;
            case name.startsWith('Public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('Settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <ConfirmDialogProvider>{app}</ConfirmDialogProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Tras un envío con errores del servidor, el foco va al primer campo con error en todos los formularios (INV-11). Se
// espera a que React pinte los mensajes y los atributos del campo antes de buscarlo.
router.on('error', (event) => {
    window.setTimeout(() => focusFirstError(event.detail.errors), 50);
});
