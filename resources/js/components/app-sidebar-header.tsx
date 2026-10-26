import { Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Menu,
    MoreHorizontal,
    PanelLeftClose,
    PanelLeftOpen,
} from 'lucide-react';
import { useState } from 'react';
import { AlertsBell } from '@/components/alerts-bell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeToggleButton from '@/components/theme-toggle-button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import WarehouseSelector from '@/components/warehouse-selector';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const { toggleSidebar, state, isMobile } = useSidebar();

    const toggleApplicationMenu = () => {
        setApplicationMenuOpen((prev) => !prev);
    };

    return (
        <header className="sticky top-0 z-40 w-full border-b border-border/80 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/85">
            <div className="flex w-full flex-col lg:flex-row lg:items-center lg:justify-between lg:px-6">
                <div className="flex w-full items-center justify-between gap-2 border-b border-border/70 px-3 py-3 sm:gap-4 lg:border-b-0 lg:px-0 lg:py-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-10 text-muted-foreground hover:text-foreground"
                            onClick={toggleSidebar}
                            aria-label="Mostrar u ocultar menú"
                        >
                            {isMobile ? (
                                <Menu className="size-4" />
                            ) : state === 'collapsed' ? (
                                <PanelLeftOpen className="size-4" />
                            ) : (
                                <PanelLeftClose className="size-4" />
                            )}
                        </Button>

                        <Link href={dashboard()} className="lg:hidden">
                            <img
                                src="/images/logo-pintech.png"
                                alt="Logo de Pintech"
                                className="h-8 w-auto object-contain"
                            />
                        </Link>
                    </div>

                    {/* En el celular la campana queda a la vista: las alertas no se esconden dentro de «…». */}
                    <div className="flex items-center gap-2 lg:hidden">
                        <AlertsBell />
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={toggleApplicationMenu}
                            className="size-10 text-muted-foreground hover:text-foreground"
                            aria-label="Más opciones"
                            aria-expanded={isApplicationMenuOpen}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </div>
                </div>

                <div
                    className={`${isApplicationMenuOpen ? 'flex' : 'hidden'} w-full items-center justify-between gap-4 px-5 py-4 shadow-sm lg:flex lg:w-auto lg:justify-end lg:px-0 lg:py-4 lg:shadow-none`}
                >
                    <div className="flex items-center gap-2">
                        <WarehouseSelector />
                        <ThemeToggleButton />
                        <div className="hidden lg:block">
                            <AlertsBell />
                        </div>
                    </div>

                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="h-10 items-center gap-2 rounded-lg px-2"
                                >
                                    <Avatar className="h-8 w-8">
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-full bg-muted text-foreground">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden max-w-28 truncate text-sm font-medium md:block">
                                        {auth.user.name}
                                    </span>
                                    <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            {breadcrumbs.length > 0 && (
                <div className="border-t border-border/60 px-4 py-2.5 text-sm text-muted-foreground">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
        </header>
    );
}
