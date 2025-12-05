import { useMemo } from 'react';
import type { Invoice } from '@/types/invoice';
import type { FilterState } from './FiltersPanel';
import { parseDate } from '@/lib/utils';

export interface SortState {
  field: keyof Invoice | 'date'; // 'date' is in Invoice, but explicit is fine
  direction: 'asc' | 'desc';
}

interface UseFilteredInvoicesProps {
  invoices: Invoice[] | undefined;
  filters: FilterState;
  search: string;
  sort: SortState;
  activeTab: 'regular' | 'tender';
}

export function useFilteredInvoices({ invoices, filters, search, sort, activeTab }: UseFilteredInvoicesProps) {
  return useMemo(() => {
    if (!invoices) return [];

    let result = [...invoices];

    // 1. Filter by Tab (Document Type)
    result = result.filter(inv => {
      const type = inv.documentType || 'regular';
      return type === activeTab;
    });

    // 2. Search
    if (search) {
      const term = search.toLowerCase();
      result = result.filter(inv => 
        inv.recipient.toLowerCase().includes(term) ||
        inv.number.toLowerCase().includes(term)
      );
    }

    // 3. Filters
    if (filters.dateFrom) {
      const fromDate = parseDate(filters.dateFrom).getTime();
      result = result.filter(inv => {
        const invDate = parseDate(inv.date).getTime();
        return invDate >= fromDate;
      });
    }
    if (filters.dateTo) {
      const toDate = parseDate(filters.dateTo);
      // Set to end of day for inclusive filtering
      toDate.setHours(23, 59, 59, 999);
      const toTime = toDate.getTime();
      
      result = result.filter(inv => {
        const invDate = parseDate(inv.date).getTime();
        return invDate <= toTime;
      });
    }
    if (filters.currency && filters.currency !== 'all') {
      result = result.filter(inv => inv.currency === filters.currency);
    }
    if (filters.minAmount) {
      result = result.filter(inv => inv.total >= parseFloat(filters.minAmount));
    }
    if (filters.maxAmount) {
      result = result.filter(inv => inv.total <= parseFloat(filters.maxAmount));
    }

    // 4. Sort
    result.sort((a, b) => {
      // Handle dates separately
      if (sort.field === 'date') {
        const aTime = parseDate(a.date).getTime();
        const bTime = parseDate(b.date).getTime();
        if (aTime < bTime) return sort.direction === 'asc' ? -1 : 1;
        if (aTime > bTime) return sort.direction === 'asc' ? 1 : -1;
        return 0;
      }

      let aVal = a[sort.field];
      let bVal = b[sort.field];

      // Handle undefined/null values - move them to the end
      if (aVal === undefined || aVal === null) return 1;
      if (bVal === undefined || bVal === null) return -1;
      if (aVal === bVal) return 0;

      // Handle strings case-insensitive
      if (typeof aVal === 'string' && typeof bVal === 'string') {
        aVal = aVal.toLowerCase();
        bVal = bVal.toLowerCase();
      }

      if (aVal < bVal) return sort.direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return sort.direction === 'asc' ? 1 : -1;
      return 0;
    });

    return result;
  }, [invoices, filters, search, sort, activeTab]);
}

