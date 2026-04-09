import type { FC } from 'react';

interface ProductionDashboardProps {
    role: string;
    userName: string;
    stats: {
        pendingOrders: number;
        activeOrders: number;
        completedToday: number;
    };
}

const ProductionDashboard: FC<ProductionDashboardProps> = ({
    role,
    userName,
    stats,
}) => {
    void stats;

    return (
        <div className="min-h-screen bg-background text-foreground">
            {/* Header */}
            <div className="from-primary to-primary/80 text-primary-foreground bg-gradient-to-r px-8 py-12">
                <div className="mx-auto max-w-6xl">
                    <h1 className="mb-2 text-4xl font-bold">
                        ⚙️ HELLO WORLD PRODUCCIÓN!
                    </h1>
                    <p className="text-primary-foreground/85 text-lg">
                        Welcome,{' '}
                        <span className="font-semibold">{userName}</span>
                    </p>
                    <div className="bg-primary-foreground/15 mt-4 inline-block rounded-full px-4 py-2">
                        <span className="text-primary-foreground/85">Role: </span>
                        <span className="text-primary-foreground font-bold tracking-wider uppercase">
                            {role}
                        </span>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="mx-auto max-w-6xl px-8 py-12">
                {/* Permissions Card */}
                <div className="mb-8 rounded-lg border border-border bg-card p-8 shadow-sm">
                    <h2 className="mb-6 text-2xl font-bold text-foreground">
                        🔐 Permissions
                    </h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-foreground">
                                Create Production Orders
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-foreground">
                                Update Inventory
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-foreground">
                                View Production Costs
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-foreground">
                                Track Formulas & Batches
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-red-600 bg-red-50 p-4">
                            <span className="mr-4 text-3xl">❌</span>
                            <span className="font-medium text-foreground">
                                Manage Users
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-red-600 bg-red-50 p-4">
                            <span className="mr-4 text-3xl">❌</span>
                            <span className="font-medium text-foreground">
                                View Sales Data
                            </span>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            ⏳
                        </div>
                        <p className="text-muted-foreground text-sm tracking-wide uppercase">
                            Pending Orders
                        </p>
                        <p className="text-3xl font-bold text-foreground">-</p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            🔄
                        </div>
                        <p className="text-muted-foreground text-sm tracking-wide uppercase">
                            Active Orders
                        </p>
                        <p className="text-3xl font-bold text-foreground">-</p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            ✨
                        </div>
                        <p className="text-muted-foreground text-sm tracking-wide uppercase">
                            Completed Today
                        </p>
                        <p className="text-3xl font-bold text-foreground">-</p>
                    </div>
                </div>

                {/* Access Routes */}
                <div className="rounded-lg border border-border bg-card p-8 shadow-sm">
                    <h2 className="mb-6 text-2xl font-bold text-foreground">
                        🗺️ Accessible Routes
                    </h2>
                    <div className="space-y-3">
                        <div className="flex items-center rounded border-l-4 border-green-500 bg-green-50 p-3">
                            <span className="text-primary mr-3 font-bold">
                                ✓
                            </span>
                            <code className="text-foreground font-mono">
                                /production/*
                            </code>
                            <span className="text-muted-foreground ml-4">
                                Production panel & workflows
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-green-500 bg-green-50 p-3">
                            <span className="text-primary mr-3 font-bold">
                                ✓
                            </span>
                            <code className="text-foreground font-mono">
                                /costs/*
                            </code>
                            <span className="text-muted-foreground ml-4">
                                Shared access (with Admin)
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-red-500 bg-red-50 p-3">
                            <span className="text-destructive mr-3 font-bold">
                                ✗
                            </span>
                            <code className="text-foreground font-mono">
                                /admin/*
                            </code>
                            <span className="text-muted-foreground ml-4">
                                Restricted to admin role
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-red-500 bg-red-50 p-3">
                            <span className="text-destructive mr-3 font-bold">
                                ✗
                            </span>
                            <code className="text-foreground font-mono">
                                /availability/*
                            </code>
                            <span className="text-muted-foreground ml-4">
                                Restricted to commercial role
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ProductionDashboard;
