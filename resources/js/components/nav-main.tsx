import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenuBadge,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group, index) => (
                <SidebarGroup
                    key={group.label || `group-${index}`}
                    className="px-2 py-0"
                >
                    {/* Un grupo sin título (Dashboard) no pinta cabecera ni deja su espacio. */}
                    {group.label && (
                        <SidebarGroupLabel className="px-2 text-xs tracking-[0.22em] text-sidebar-foreground/45 uppercase">
                            {group.label}
                        </SidebarGroupLabel>
                    )}
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {group.items.map((item) => {
                                const itemIsActive = isCurrentUrl(item.href);

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={itemIsActive}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        {item.badge !== undefined && (
                                            <SidebarMenuBadge className="rounded-md bg-sidebar-primary/20 px-1.5 text-xs font-semibold text-sidebar-primary">
                                                {item.badge}
                                            </SidebarMenuBadge>
                                        )}
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            ))}
        </>
    );
}
