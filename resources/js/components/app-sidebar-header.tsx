import { Bell, Search } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-slate-600 hover:text-slate-900" />
                <div className="min-w-0">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    className="hidden h-9 items-center gap-2 border-slate-200 bg-white text-slate-600 md:inline-flex"
                >
                    <Search className="size-4" />
                    <span className="text-sm">Buscar</span>
                    <span className="rounded border border-slate-200 px-1.5 py-0.5 text-[10px] tracking-wider text-slate-500 uppercase">
                        ⌘ K
                    </span>
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative text-slate-600 hover:text-slate-900"
                    aria-label="Notificaciones"
                >
                    <Bell className="size-5" />
                    <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                        3
                    </span>
                </Button>
                <AppearanceTabs compact />
            </div>
        </header>
    );
}
