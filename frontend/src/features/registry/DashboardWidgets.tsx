import { useMemo } from 'react';
import type { Invoice } from '@/types/invoice';
import { FileText, Calculator, TrendingUp, Users } from 'lucide-react';

interface DashboardWidgetsProps {
  invoices: Invoice[];
  isLoading: boolean;
}

export function DashboardWidgets({ invoices, isLoading }: DashboardWidgetsProps) {
  const stats = useMemo(() => {
    if (!invoices.length) return null;

    const totalCount = invoices.length;
    const totalSum = invoices.reduce((acc, inv) => acc + inv.total, 0);
    
    // Unique clients
    const uniqueClients = new Set(invoices.map(inv => inv.recipient.trim())).size;

    // Current month count (assuming date is YYYY-MM-DD)
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    
    const thisMonthCount = invoices.filter(inv => {
      const d = new Date(inv.date);
      return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
    }).length;

    return {
      totalCount,
      totalSum,
      uniqueClients,
      thisMonthCount
    };
  }, [invoices]);

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="bg-white rounded-lg shadow p-4 animate-pulse h-24"></div>
        ))}
      </div>
    );
  }

  if (!stats) return null;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {/* Total Count */}
      <div className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500 font-medium">Всего КП</p>
          <p className="text-2xl font-bold text-gray-800">{stats.totalCount}</p>
        </div>
        <div className="p-3 bg-blue-50 rounded-full text-blue-600">
          <FileText size={24} />
        </div>
      </div>

      {/* Total Sum */}
      <div className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500 font-medium">Сумма (RUB)</p>
          <p className="text-2xl font-bold text-gray-800">
            {(stats.totalSum / 1000000).toFixed(1)}M
          </p>
        </div>
        <div className="p-3 bg-green-50 rounded-full text-green-600">
          <Calculator size={24} />
        </div>
      </div>

      {/* This Month */}
      <div className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500 font-medium">В этом месяце</p>
          <p className="text-2xl font-bold text-gray-800">+{stats.thisMonthCount}</p>
        </div>
        <div className="p-3 bg-purple-50 rounded-full text-purple-600">
          <TrendingUp size={24} />
        </div>
      </div>

      {/* Clients */}
      <div className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500 font-medium">Клиентов</p>
          <p className="text-2xl font-bold text-gray-800">{stats.uniqueClients}</p>
        </div>
        <div className="p-3 bg-orange-50 rounded-full text-orange-600">
          <Users size={24} />
        </div>
      </div>
    </div>
  );
}

