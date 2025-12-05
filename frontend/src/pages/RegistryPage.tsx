import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { useInvoices } from '@/features/registry/useInvoices';
import { RegistryTable } from '@/features/registry/RegistryTable';
import { DashboardWidgets } from '@/features/registry/DashboardWidgets';
import { FiltersPanel, type FilterState } from '@/features/registry/FiltersPanel';
import { Pagination } from '@/features/registry/Pagination';
import { useFilteredInvoices, type SortState } from '@/features/registry/useFilteredInvoices';
import { useInvoiceActions } from '@/features/registry/useInvoiceActions';
import { CopyModal } from '@/features/registry/CopyModal';
import { FileText, Gavel, Plus, LogOut, Search } from 'lucide-react';
import type { Invoice } from '@/types/invoice';
import { cn } from '@/lib/utils';

export default function RegistryPage() {
  const { user, logout } = useAuth();
  const { data: invoices, isLoading, error, refetch } = useInvoices();
  const { deleteInvoice, copyInvoice } = useInvoiceActions();

  // State
  const [activeTab, setActiveTab] = useState<'regular' | 'tender'>('regular');
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState<FilterState>({
    dateFrom: '',
    dateTo: '',
    currency: 'all',
    minAmount: '',
    maxAmount: '',
  });
  const [sort] = useState<SortState>({ field: 'date', direction: 'desc' });
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  
  // Modals
  const [copyModalOpen, setCopyModalOpen] = useState(false);
  const [invoiceToCopy, setInvoiceToCopy] = useState<Invoice | null>(null);

  // Permissions
  const canDelete = user?.email.includes('admin');
  const isReadOnly = user?.email.includes('kka');

  // Data Processing
  const filteredInvoices = useFilteredInvoices({
    invoices,
    filters,
    search,
    sort,
    activeTab
  });

  const paginatedInvoices = useMemo(() => {
    const start = (currentPage - 1) * itemsPerPage;
    return filteredInvoices.slice(start, start + itemsPerPage);
  }, [filteredInvoices, currentPage, itemsPerPage]);

  const totalPages = Math.ceil(filteredInvoices.length / itemsPerPage);

  // Handlers
  const handleFilterChange = (newFilters: FilterState) => {
    setFilters(newFilters);
    setCurrentPage(1);
  };

  const handleResetFilters = () => {
    setFilters({
      dateFrom: '',
      dateTo: '',
      currency: 'all',
      minAmount: '',
      maxAmount: '',
    });
    setSearch('');
    setCurrentPage(1);
  };

  const handleDelete = async (invoice: Invoice) => {
    if (!confirm(`Вы уверены, что хотите удалить КП ${invoice.number}?`)) return;
    try {
      await deleteInvoice.mutateAsync({ filename: invoice.filename });
    } catch (err: any) {
      alert(err.message);
    }
  };

  const handleCopy = (invoice: Invoice) => {
    setInvoiceToCopy(invoice);
    setCopyModalOpen(true);
  };

  const confirmCopy = async (type: 'regular' | 'tender') => {
    if (!invoiceToCopy) return;
    try {
      await copyInvoice.mutateAsync({ filename: invoiceToCopy.filename, documentType: type });
      setCopyModalOpen(false);
      setInvoiceToCopy(null);
    } catch (err: any) {
      alert(err.message);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg relative sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 pt-4 pb-0">
          <div className="flex items-center justify-between gap-4 mb-4">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 bg-white rounded flex items-center justify-center text-blue-600 font-bold text-xs shrink-0">
                VEC
              </div>
              <div className="hidden md:block">
                <h1 className="text-xl font-bold">Реестр КП</h1>
                <p className="text-xs text-blue-200">ООО "Вектор"</p>
              </div>
            </div>

            {/* Search Bar */}
            <div className="flex-1 max-w-md mx-4">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-blue-300" size={16} />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => { setSearch(e.target.value); setCurrentPage(1); }}
                  placeholder="Поиск по контрагенту или номеру..."
                  className="w-full pl-9 pr-4 py-2 rounded-lg bg-blue-700/50 border border-blue-500/30 text-white placeholder-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-400 text-sm transition-colors"
                />
              </div>
            </div>

            <div className="flex items-center gap-3">
              {user && (
                <div className="hidden md:flex items-center gap-2 rounded-lg px-3 py-1.5 bg-blue-700/50 backdrop-blur-sm">
                   <span className="text-sm text-white font-medium truncate max-w-[150px]">
                     {user.email}
                   </span>
                </div>
              )}
              <button
                onClick={() => logout()}
                className="p-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors shadow-md"
                title="Выйти"
              >
                <LogOut size={20} />
              </button>
            </div>
          </div>
          
          {/* Tabs */}
          <div className="flex items-end gap-1 mt-4">
            <button
              onClick={() => { setActiveTab('regular'); setCurrentPage(1); }}
              className={cn(
                "relative px-6 py-2.5 font-medium text-sm transition-all rounded-t-lg flex items-center gap-2",
                activeTab === 'regular' 
                  ? "bg-gray-50 text-gray-900 shadow-[0_-2px_10px_rgba(0,0,0,0.1)] z-10" 
                  : "text-blue-100 hover:text-white hover:bg-blue-700/30"
              )}
            >
              <FileText size={16} />
              КП
            </button>
            <button
              onClick={() => { setActiveTab('tender'); setCurrentPage(1); }}
              className={cn(
                "relative px-6 py-2.5 font-medium text-sm transition-all rounded-t-lg flex items-center gap-2",
                activeTab === 'tender' 
                  ? "bg-gray-50 text-gray-900 shadow-[0_-2px_10px_rgba(0,0,0,0.1)] z-10" 
                  : "text-blue-100 hover:text-white hover:bg-blue-700/30"
              )}
            >
              <Gavel size={16} />
              Тендеры
            </button>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-8">
        
        <DashboardWidgets invoices={filteredInvoices} isLoading={isLoading} />

        <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h2 className="text-xl font-bold text-gray-800">
              {activeTab === 'regular' ? 'Коммерческие предложения' : 'Тендерные предложения'}
              <span className="ml-2 text-sm font-normal text-gray-500 bg-white px-2 py-0.5 rounded-full shadow-sm border">
                {filteredInvoices.length}
              </span>
            </h2>
            
            {!isReadOnly && (
              <Link 
                to={`/editor?type=${activeTab}`} 
                className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md font-medium transition-colors flex items-center gap-2"
              >
                <Plus size={20} />
                Создать {activeTab === 'regular' ? 'КП' : 'Тендер'}
              </Link>
            )}
        </div>

        <FiltersPanel 
          filters={filters} 
          onFilterChange={handleFilterChange} 
          onReset={handleResetFilters} 
        />

        {/* Content */}
        {isLoading ? (
          <div className="flex justify-center py-12">
            <div className="animate-spin rounded-full h-12 w-12 border-4 border-blue-200 border-t-blue-600"></div>
          </div>
        ) : error ? (
          <div className="bg-red-50 border border-red-200 text-red-700 p-6 rounded-lg text-center flex flex-col items-center gap-4">
            <p className="font-medium">Ошибка загрузки: {(error as Error).message}</p>
            <button 
              onClick={() => refetch()}
              className="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-800 rounded-md transition-colors text-sm font-medium"
            >
              Попробовать снова
            </button>
          </div>
        ) : (
          <>
            <RegistryTable 
              invoices={paginatedInvoices} 
              onCopy={handleCopy}
              onDelete={handleDelete}
              canDelete={canDelete}
            />
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={setCurrentPage}
              itemsPerPage={itemsPerPage}
              onItemsPerPageChange={(limit) => { setItemsPerPage(limit); setCurrentPage(1); }}
              totalItems={filteredInvoices.length}
            />
          </>
        )}
      </div>

      <CopyModal
        isOpen={copyModalOpen}
        onClose={() => setCopyModalOpen(false)}
        onConfirm={confirmCopy}
        isPending={copyInvoice.isPending}
      />
    </div>
  );
}
