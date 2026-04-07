import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Menu,
    MoreHorizontal,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeToggleButton from '@/components/theme-toggle-button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const { toggleSidebar, state, isMobile } = useSidebar();

    const toggleApplicationMenu = () => {
        setApplicationMenuOpen((prev) => !prev);
    };

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                searchInputRef.current?.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, []);

    return (
        <header className="sticky top-0 z-40 w-full border-b border-border/80 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/85">
            <div className="flex w-full flex-col lg:flex-row lg:items-center lg:justify-between lg:px-6">
                <div className="flex w-full items-center justify-between gap-2 border-b border-border/70 px-3 py-3 sm:gap-4 lg:border-b-0 lg:px-0 lg:py-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-10 w-10 border-border text-muted-foreground hover:text-foreground lg:h-11 lg:w-11"
                            onClick={toggleSidebar}
                            aria-label="Toggle sidebar"
                        >
                            {isMobile ? (
                                <Menu className="h-4 w-4" />
                            ) : state === 'collapsed' ? (
                                <ChevronRight className="h-4 w-4" />
                            ) : (
                                <ChevronLeft className="h-4 w-4" />
                            )}
                        </Button>

                        <Link href={dashboard()} className="lg:hidden">
                            <img
                                src="/images/logo-pintech.png"
                                alt="Pintech logo"
                                className="h-8 w-auto object-contain"
                            />
                        </Link>
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={toggleApplicationMenu}
                        className="h-10 w-10 text-muted-foreground hover:text-foreground lg:hidden"
                        aria-label="Open application menu"
                    >
                        <MoreHorizontal className="h-5 w-5" />
                    </Button>

                    <div className="relative hidden lg:block">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2" />
                        <input
                            ref={searchInputRef}
                            type="text"
                            placeholder="Buscar o escribir comando..."
                            className="h-11 w-[430px] rounded-lg border border-input bg-background py-2.5 pr-14 pl-11 text-sm text-foreground placeholder:text-muted-foreground shadow-xs outline-none transition-[border-color,box-shadow] focus:border-ring focus:ring-3 focus:ring-ring/20"
                        />
                        <button
                            type="button"
                            onClick={() => searchInputRef.current?.focus()}
                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 inline-flex -translate-y-1/2 items-center gap-0.5 rounded-md border border-input bg-muted/60 px-2 py-1 text-xs"
                        >
                            <span>⌘</span>
                            <span>K</span>
                        </button>
                    </div>
                </div>

                <div
                    className={`${isApplicationMenuOpen ? 'flex' : 'hidden'} w-full items-center justify-between gap-4 px-5 py-4 shadow-sm lg:flex lg:w-auto lg:justify-end lg:px-0 lg:py-4 lg:shadow-none`}
                >
                    <div className="flex items-center gap-2">
                        <ThemeToggleButton />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="relative h-10 w-10 text-muted-foreground hover:text-foreground"
                                    aria-label="Notificaciones"
                                >
                                    <Bell className="h-5 w-5" />
                                    <span className="absolute top-1 right-1 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-background" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-80">
                                <DropdownMenuLabel>
                                    Notificaciones
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem>
                                        Stock crítico en Materia Prima A
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        Orden OP-2026-014 pendiente de aprobación
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        Nuevo ingreso en inventario final
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
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
                                        <AvatarFallback className="bg-muted text-foreground rounded-full">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden max-w-28 truncate text-sm font-medium md:block">
                                        {auth.user.name}
                                    </span>
                                    <ChevronDown className="text-muted-foreground h-4 w-4" />
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
                <div className="text-muted-foreground border-t border-border/60 px-4 py-2.5 text-sm">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
        </header>
    );
}
