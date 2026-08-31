import type { LucideIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';

interface DashboardHeaderProps {
    userName: string;
    role: string;
    title: string;
    subtitle: string;
    icon: LucideIcon;
    iconBgClassName?: string;
    badgeBorderClassName?: string;
    badgeBgClassName?: string;
    badgeTextClassName?: string;
}

export function DashboardHeader({
    userName,
    role,
    title,
    subtitle,
    icon: Icon,
    iconBgClassName = 'bg-blue-600 shadow-blue-600/20',
    badgeBorderClassName = 'border-blue-600/30',
    badgeBgClassName = 'bg-blue-600/20',
    badgeTextClassName = 'text-blue-300',
}: DashboardHeaderProps) {
    return (
        <div className="relative overflow-hidden bg-slate-900 px-8 py-12 text-white">
            <div className="absolute inset-0 bg-linear-to-br from-blue-600/20 to-transparent" />
            <div className="relative mx-auto max-w-6xl">
                <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div className="flex items-start gap-4">
                        <div
                            className={`rounded-xl p-3 shadow-lg ${iconBgClassName}`}
                        >
                            <Icon className="h-8 w-8" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
                                {title}
                            </h1>
                            <p className="mt-1 text-slate-400">
                                {subtitle.replace('{name}', userName)}
                            </p>
                        </div>
                    </div>
                    <Badge
                        className={`w-fit ${badgeBorderClassName} ${badgeBgClassName} ${badgeTextClassName}`}
                    >
                        {role.toUpperCase()}
                    </Badge>
                </div>
            </div>
        </div>
    );
}
