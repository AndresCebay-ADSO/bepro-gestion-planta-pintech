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
        <div className="min-h-screen bg-gradient-to-br from-orange-50 to-orange-100">
            {/* Header */}
            <div className="bg-orange-600 px-8 py-12 text-white">
                <div className="mx-auto max-w-6xl">
                    <h1 className="mb-2 text-4xl font-bold">
                        ⚙️ HELLO WORLD PRODUCCIÓN!
                    </h1>
                    <p className="text-lg text-orange-100">
                        Welcome,{' '}
                        <span className="font-semibold">{userName}</span>
                    </p>
                    <div className="mt-4 inline-block rounded-full bg-orange-700 px-4 py-2">
                        <span className="text-orange-100">Role: </span>
                        <span className="font-bold tracking-wider text-white uppercase">
                            {role}
                        </span>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="mx-auto max-w-6xl px-8 py-12">
                {/* Permissions Card */}
                <div className="mb-8 rounded-lg bg-white p-8 shadow-lg">
                    <h2 className="mb-6 text-2xl font-bold text-orange-900">
                        🔐 Permissions
                    </h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-gray-800">
                                Create Production Orders
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-gray-800">
                                Update Inventory
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-gray-800">
                                View Production Costs
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-orange-600 bg-orange-50 p-4">
                            <span className="mr-4 text-3xl">✅</span>
                            <span className="font-medium text-gray-800">
                                Track Formulas & Batches
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-red-600 bg-red-50 p-4">
                            <span className="mr-4 text-3xl">❌</span>
                            <span className="font-medium text-gray-800">
                                Manage Users
                            </span>
                        </div>
                        <div className="flex items-center rounded-lg border-l-4 border-red-600 bg-red-50 p-4">
                            <span className="mr-4 text-3xl">❌</span>
                            <span className="font-medium text-gray-800">
                                View Sales Data
                            </span>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="rounded-lg bg-white p-6 shadow-lg">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            ⏳
                        </div>
                        <p className="text-sm tracking-wide text-gray-600 uppercase">
                            Pending Orders
                        </p>
                        <p className="text-3xl font-bold text-gray-900">-</p>
                    </div>
                    <div className="rounded-lg bg-white p-6 shadow-lg">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            🔄
                        </div>
                        <p className="text-sm tracking-wide text-gray-600 uppercase">
                            Active Orders
                        </p>
                        <p className="text-3xl font-bold text-gray-900">-</p>
                    </div>
                    <div className="rounded-lg bg-white p-6 shadow-lg">
                        <div className="mb-2 text-4xl font-bold text-orange-600">
                            ✨
                        </div>
                        <p className="text-sm tracking-wide text-gray-600 uppercase">
                            Completed Today
                        </p>
                        <p className="text-3xl font-bold text-gray-900">-</p>
                    </div>
                </div>

                {/* Access Routes */}
                <div className="rounded-lg bg-white p-8 shadow-lg">
                    <h2 className="mb-6 text-2xl font-bold text-orange-900">
                        🗺️ Accessible Routes
                    </h2>
                    <div className="space-y-3">
                        <div className="flex items-center rounded border-l-4 border-green-500 bg-green-50 p-3">
                            <span className="mr-3 font-bold text-green-600">
                                ✓
                            </span>
                            <code className="font-mono text-gray-800">
                                /production/*
                            </code>
                            <span className="ml-4 text-gray-600">
                                Production panel & workflows
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-green-500 bg-green-50 p-3">
                            <span className="mr-3 font-bold text-green-600">
                                ✓
                            </span>
                            <code className="font-mono text-gray-800">
                                /costs/*
                            </code>
                            <span className="ml-4 text-gray-600">
                                Shared access (with Admin)
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-red-500 bg-red-50 p-3">
                            <span className="mr-3 font-bold text-red-600">
                                ✗
                            </span>
                            <code className="font-mono text-gray-800">
                                /admin/*
                            </code>
                            <span className="ml-4 text-gray-600">
                                Restricted to admin role
                            </span>
                        </div>
                        <div className="flex items-center rounded border-l-4 border-red-500 bg-red-50 p-3">
                            <span className="mr-3 font-bold text-red-600">
                                ✗
                            </span>
                            <code className="font-mono text-gray-800">
                                /availability/*
                            </code>
                            <span className="ml-4 text-gray-600">
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
