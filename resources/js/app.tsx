import { createInertiaApp } from '@inertiajs/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

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
                return null;
            // Login y recuperación tienen UI propia (split)
            case name === 'auth/login':
            case name === 'auth/forgot-password':
                return null;
            case name.startsWith('Public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return <TooltipProvider delayDuration={0}>{app}</TooltipProvider>;
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
