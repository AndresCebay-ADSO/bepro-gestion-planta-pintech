import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import type { NavGroup } from '@/types';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.label} className="px-2 py-0">
                    <SidebarGroupLabel className="px-2 text-[10px] tracking-[0.22em] text-sidebar-foreground/45 uppercase">
                        {group.label}
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {group.items.map((item) => {
                                const itemIsActive = isCurrentUrl(item.href);
                                const isDisabled = Boolean(item.disabled);

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild={!isDisabled}
                                            isActive={!isDisabled && itemIsActive}
                                            tooltip={{
                                                children:
                                                    item.disabledLabel ??
                                                    item.title,
                                            }}
                                            className={cn(
                                                isDisabled &&
                                                    'cursor-not-allowed text-sidebar-foreground/50 hover:bg-transparent hover:text-sidebar-foreground/50',
                                            )}
                                        >
                                            {isDisabled ? (
                                                <div className="flex items-center gap-2">
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                    <Lock className="ml-auto size-3.5 opacity-60" />
                                                </div>
                                            ) : (
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                >
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            )}
                                        </SidebarMenuButton>
                                        {item.badge !== undefined && (
                                            <SidebarMenuBadge className="rounded-md bg-sidebar-primary/20 px-1.5 text-[11px] font-semibold text-sidebar-primary">
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
