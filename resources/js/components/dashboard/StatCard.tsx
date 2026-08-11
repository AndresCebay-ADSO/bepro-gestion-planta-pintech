import type { LucideIcon } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';

interface StatCardProps {
    icon: LucideIcon;
    label: string;
    value: number;
    iconClassName?: string;
}

export function StatCard({ icon: Icon, label, value, iconClassName }: StatCardProps) {
    return (
        <Card className="border-none shadow-lg">
            <CardContent className="flex items-center gap-4 p-5">
                <div className={`rounded-full p-3 ${iconClassName}`}>
                    <Icon className="h-5 w-5" />
                </div>
                <div>
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="text-3xl font-bold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}
