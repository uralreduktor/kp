import { useState } from 'react';
import { ChevronDown, ChevronUp, X } from 'lucide-react';

export interface FilterState {
  dateFrom: string;
  dateTo: string;
  currency: string;
  minAmount: string;
  maxAmount: string;
}

interface FiltersPanelProps {
  filters: FilterState;
  onFilterChange: (newFilters: FilterState) => void;
  onReset: () => void;
}

export function FiltersPanel({ filters, onFilterChange, onReset }: FiltersPanelProps) {
  const [isExpanded, setIsExpanded] = useState(false);

  const handleChange = (key: keyof FilterState, value: string) => {
    onFilterChange({ ...filters, [key]: value });
  };

  const hasActiveFilters = Object.values(filters).some(Boolean) && filters.currency !== 'all';

  return (
    <div className="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
      {/* Header */}
      <div className="w-full px-4 py-3 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors">
        <button
          onClick={() => setIsExpanded(!isExpanded)}
          className="flex items-center gap-3 flex-1 text-left focus:outline-none"
        >
          {isExpanded ? <ChevronUp className="text-gray-500" size={20} /> : <ChevronDown className="text-gray-500" size={20} />}
          <span className="font-medium text-gray-700">Фильтры</span>
          
          {/* Badges */}
          <div className="flex items-center gap-2 flex-wrap">
            {filters.dateFrom && (
              <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full">от {filters.dateFrom}</span>
            )}
            {filters.dateTo && (
              <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full">до {filters.dateTo}</span>
            )}
            {filters.currency && filters.currency !== 'all' && (
              <span className="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">{filters.currency}</span>
            )}
            {(filters.minAmount || filters.maxAmount) && (
               <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">
                 {filters.minAmount || '0'} - {filters.maxAmount || '∞'}
               </span>
            )}
          </div>
        </button>

        <div className="flex items-center gap-2">
          {hasActiveFilters && (
            <button
              onClick={(e) => { e.stopPropagation(); onReset(); }}
              className="flex items-center gap-1 px-2 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded transition-colors cursor-pointer border-none"
            >
              <X size={12} /> Сбросить
            </button>
          )}
          <button 
            onClick={() => setIsExpanded(!isExpanded)}
            className="text-sm text-gray-500 hover:text-gray-700 focus:outline-none"
          >
            {isExpanded ? 'Свернуть' : 'Развернуть'}
          </button>
        </div>
      </div>

      {/* Content */}
      {isExpanded && (
        <div className="p-4 border-t border-gray-200">
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            {/* Date From */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Дата от</label>
              <input
                type="date"
                value={filters.dateFrom}
                onChange={(e) => handleChange('dateFrom', e.target.value)}
                className="w-full px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>

            {/* Date To */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Дата до</label>
              <input
                type="date"
                value={filters.dateTo}
                onChange={(e) => handleChange('dateTo', e.target.value)}
                className="w-full px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>

            {/* Currency */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Валюта</label>
              <select
                value={filters.currency}
                onChange={(e) => handleChange('currency', e.target.value)}
                className="w-full px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white"
              >
                <option value="all">Все</option>
                <option value="Руб.">Руб.</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="CNY">CNY</option>
              </select>
            </div>

            {/* Min Amount */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Мин. сумма</label>
              <input
                type="number"
                value={filters.minAmount}
                onChange={(e) => handleChange('minAmount', e.target.value)}
                placeholder="0"
                className="w-full px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>

            {/* Max Amount */}
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Макс. сумма</label>
              <input
                type="number"
                value={filters.maxAmount}
                onChange={(e) => handleChange('maxAmount', e.target.value)}
                placeholder="∞"
                className="w-full px-2 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


