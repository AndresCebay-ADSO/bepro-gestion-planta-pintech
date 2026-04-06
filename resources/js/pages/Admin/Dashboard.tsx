import { FC } from 'react';

interface AdminDashboardProps {
  role: string;
  userName: string;
  stats: {
    totalUsers: number;
    totalProducts: number;
    totalWarehouses: number;
  };
}

const AdminDashboard: FC<AdminDashboardProps> = ({ role, userName, stats }) => {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100">
      {/* Header */}
      <div className="bg-blue-600 text-white px-8 py-12">
        <div className="max-w-6xl mx-auto">
          <h1 className="text-4xl font-bold mb-2">👋 HELLO WORLD ADMIN!</h1>
          <p className="text-blue-100 text-lg">Welcome, <span className="font-semibold">{userName}</span></p>
          <div className="mt-4 inline-block bg-blue-700 px-4 py-2 rounded-full">
            <span className="text-blue-100">Role: </span>
            <span className="font-bold text-white uppercase tracking-wider">{role}</span>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-6xl mx-auto px-8 py-12">
        {/* Permissions Card */}
        <div className="bg-white rounded-lg shadow-lg p-8 mb-8">
          <h2 className="text-2xl font-bold text-blue-900 mb-6">🔐 Permissions</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="flex items-center p-4 bg-blue-50 rounded-lg border-l-4 border-blue-600">
              <span className="text-3xl mr-4">✅</span>
              <span className="text-gray-800 font-medium">Full System Access</span>
            </div>
            <div className="flex items-center p-4 bg-blue-50 rounded-lg border-l-4 border-blue-600">
              <span className="text-3xl mr-4">✅</span>
              <span className="text-gray-800 font-medium">User Management</span>
            </div>
            <div className="flex items-center p-4 bg-blue-50 rounded-lg border-l-4 border-blue-600">
              <span className="text-3xl mr-4">✅</span>
              <span className="text-gray-800 font-medium">Role Administration</span>
            </div>
            <div className="flex items-center p-4 bg-blue-50 rounded-lg border-l-4 border-blue-600">
              <span className="text-3xl mr-4">✅</span>
              <span className="text-gray-800 font-medium">Database Configuration</span>
            </div>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-lg p-6">
            <div className="text-blue-600 text-4xl font-bold mb-2">👥</div>
            <p className="text-gray-600 text-sm uppercase tracking-wide">Total Users</p>
            <p className="text-3xl font-bold text-gray-900">-</p>
          </div>
          <div className="bg-white rounded-lg shadow-lg p-6">
            <div className="text-blue-600 text-4xl font-bold mb-2">📦</div>
            <p className="text-gray-600 text-sm uppercase tracking-wide">Total Products</p>
            <p className="text-3xl font-bold text-gray-900">-</p>
          </div>
          <div className="bg-white rounded-lg shadow-lg p-6">
            <div className="text-blue-600 text-4xl font-bold mb-2">🏭</div>
            <p className="text-gray-600 text-sm uppercase tracking-wide">Warehouses</p>
            <p className="text-3xl font-bold text-gray-900">-</p>
          </div>
        </div>

        {/* Access Routes */}
        <div className="bg-white rounded-lg shadow-lg p-8">
          <h2 className="text-2xl font-bold text-blue-900 mb-6">🗺️ Accessible Routes</h2>
          <div className="space-y-3">
            <div className="flex items-center p-3 bg-green-50 border-l-4 border-green-500 rounded">
              <span className="text-green-600 font-bold mr-3">✓</span>
              <code className="text-gray-800 font-mono">/admin/*</code>
              <span className="ml-4 text-gray-600">Complete administrative panel</span>
            </div>
            <div className="flex items-center p-3 bg-green-50 border-l-4 border-green-500 rounded">
              <span className="text-green-600 font-bold mr-3">✓</span>
              <code className="text-gray-800 font-mono">/costs/*</code>
              <span className="ml-4 text-gray-600">Shared access (with Production)</span>
            </div>
            <div className="flex items-center p-3 bg-red-50 border-l-4 border-red-500 rounded">
              <span className="text-red-600 font-bold mr-3">✗</span>
              <code className="text-gray-800 font-mono">/production/*</code>
              <span className="ml-4 text-gray-600">Restricted to production role</span>
            </div>
            <div className="flex items-center p-3 bg-red-50 border-l-4 border-red-500 rounded">
              <span className="text-red-600 font-bold mr-3">✗</span>
              <code className="text-gray-800 font-mono">/availability/*</code>
              <span className="ml-4 text-gray-600">Restricted to commercial role</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;
