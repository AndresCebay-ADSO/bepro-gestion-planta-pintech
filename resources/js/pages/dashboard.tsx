import { Head } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-border bg-card">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/15" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-border bg-card">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/15" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-border bg-card">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/15" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-border bg-card md:min-h-min">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/15" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
