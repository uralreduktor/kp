import axios, { isAxiosError } from 'axios';
import { z } from 'zod';
import { ApiFullInvoiceSchema } from '@/types/api-schema';
import type { InvoiceFormData, ReducerSpecs } from './schema';

// Типы для DaData
interface DaDataSuggestion<T = Record<string, unknown>> {
  value: string;
  unrestricted_value: string;
  data: T;
}

interface LegacyItem {
  id?: string;
  description?: string;
  type?: string; // Legacy field
  model?: string;
  name?: string; // Legacy field
  quantity?: number | string;
  price?: number | string;
  reducerSpecs?: Record<string, unknown> | unknown[];
}

interface TenderImportResponse {
  success: boolean;
  data?: unknown;
  error?: string;
}

export const invoiceService = {
  /**
   * Transform legacy invoice format to new format
   */
  transformLegacyData(legacyData: z.infer<typeof ApiFullInvoiceSchema>): InvoiceFormData {
    // Validate structure loosely before transformation using Zod (runtime check)
    // This ensures we at least have an object to work with, though our transformer handles most edge cases.
    // Real strict validation happens in the Form Schema (on submit)
    
    // Helper function to generate ID
    const generateId = () => {
      if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
      }
      return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    };

    // Transform items
    const items = (legacyData.items || []).map((item: LegacyItem, index: number) => {
      // В старом формате: type содержит описание, name может быть меткой
      const description = item.description || item.type || '';
      const model = item.model || (item.name && item.name !== 'Модель' ? item.name : '');
      
      // reducerSpecs может быть в корне или в каждом item
      let reducerSpecs = item.reducerSpecs || legacyData.reducerSpecs || {};
      
      // PHP backend often returns empty object as empty array []
      if (Array.isArray(reducerSpecs)) {
        reducerSpecs = {};
      }
      
      return {
        id: item.id || generateId(),
        description: description || `Позиция ${index + 1}`,
        model: model || '',
        quantity: Number(item.quantity) || 1,
        price: Number(item.price) || 0,
        reducerSpecs: reducerSpecs as ReducerSpecs,
      };
    });

    // Transform commercial terms
    const commercialTerms = {
      incoterm: legacyData.incoterm || legacyData.commercialTerms?.incoterm || '',
      deliveryPlace: legacyData.deliveryPlace || legacyData.commercialTerms?.deliveryPlace || '',
      deliveryTime: legacyData.deliveryTime || legacyData.commercialTerms?.deliveryTime || '',
      paymentTerms: legacyData.paymentTerms || legacyData.commercialTerms?.paymentTerms || '',
      warranty: legacyData.warranty || legacyData.commercialTerms?.warranty || '',
    };

    // Transform contact
    const contact = {
      person: legacyData.contactPerson || legacyData.contact?.person || '',
      position: legacyData.position || legacyData.contact?.position || '',
      email: legacyData.contactEmail || legacyData.contact?.email || '',
      phone: legacyData.contactPhone || legacyData.contact?.phone || '',
    };

    // Normalize currency
    let currency = legacyData.currency || 'Руб.';
    if (currency === 'RUB' || currency === 'руб' || currency === 'руб.') {
      currency = 'Руб.';
    }

    // Normalize date to YYYY-MM-DD
    const normalizeDate = (d: string | undefined): string => {
      if (!d) return '';
      // Handle DD.MM.YYYY
      if (/^\d{2}\.\d{2}\.\d{4}$/.test(d)) {
        const [day, month, year] = d.split('.');
        return `${year}-${month}-${day}`;
      }
      // Handle YYYY-MM-DD HH:mm:ss
      if (d.includes(' ')) {
        return d.split(' ')[0];
      }
      return d;
    };

    return {
      filename: legacyData.filename || ((legacyData as Record<string, unknown>)._metadata as { filename?: string } | undefined)?.filename,
      number: legacyData.number || '',
      date: normalizeDate(legacyData.date),
      validUntil: normalizeDate(legacyData.validUntil),
      recipient: legacyData.recipient || '',
      recipientINN: legacyData.recipientINN || '',
      recipientAddress: legacyData.recipientAddress || '',
      currency,
      items,
      commercialTerms,
      contact,
      organizationId: legacyData.organizationId || '',
      documentType: legacyData.documentType || 'regular',
      tenderId: legacyData.tenderId || '',
      tenderPlatform: legacyData.tenderPlatform || '',
      tenderLink: legacyData.tenderLink || '',
    };
  },

  /**
   * Load invoice by filename
   */
  async load(filename: string): Promise<InvoiceFormData> {
    // Using new Python API
    const { data } = await axios.get(`/api/invoices/${encodeURIComponent(filename)}`);
    
    // FastAPI returns data directly
    const invoiceData = data;

    // Runtime Validation: Check if the data resembles an invoice
    const parsed = ApiFullInvoiceSchema.safeParse(invoiceData);
    if (!parsed.success) {
      console.error('Invoice Data Validation Error:', parsed.error);
      throw new Error('Получены некорректные данные от сервера');
    }

    // Transform legacy format to new format
    return this.transformLegacyData(parsed.data);
  },

  /**
   * Save invoice
   */
  async save(data: InvoiceFormData & { _filename?: string }): Promise<{ success: boolean; filename: string; error?: string }> {
    const payload = {
      ...data,
      // Ensure numeric fields are numbers
      items: data.items.map(item => ({
        ...item,
        quantity: Number(item.quantity),
        price: Number(item.price),
      }))
    };

    // Using new Python API
    const response = await axios.post('/api/invoices', payload);
    return response.data;
  },

  /**
   * Get next invoice number
   */
  async getNextNumber(year: number = new Date().getFullYear()): Promise<string> {
    try {
      // Using new Python API
      const { data } = await axios.get(`/api/invoices/next-number`);
      return data.number || `VEC-${year}-001`;
    } catch {
      return `VEC-${year}-001`;
    }
  },

  /**
   * Search companies by name (DaData)
   */
  async searchCompanies(query: string, count: number = 5): Promise<DaDataSuggestion[]> {
    try {
      // Using new Python API
      const { data } = await axios.get(`/api/suggest/party`, {
        params: { query, count }
      });
      return data.suggestions || [];
    } catch (error) {
      console.error('Company search error:', error);
      return [];
    }
  },

  /**
   * Get company info by INN
   */
  async getCompanyByINN(inn: string): Promise<DaDataSuggestion | null> {
    try {
      // Using new Python API
      const { data } = await axios.get(`/api/suggest/party`, {
        params: { query: inn, count: 1 }
      });
      
      if (data.suggestions && data.suggestions.length > 0) {
        return data.suggestions[0];
      }
      return null;
    } catch (error) {
      console.error('Get company by INN error:', error);
      return null;
    }
  },

  /**
   * Search addresses (DaData)
   */
  async searchAddresses(query: string, count: number = 10): Promise<DaDataSuggestion[]> {
    try {
      // Using new Python API
      const { data } = await axios.get(`/api/suggest/address`, {
        params: { query, count }
      });
      return data.suggestions || [];
    } catch (error) {
      console.error('Address search error:', error);
      return [];
    }
  },

  /**
   * Parse tender data from URL
   */
  async importTenderData(url: string): Promise<TenderImportResponse> {
    try {
      // Using new Python API directly (it already exists in ParsingService)
      // ParsingService router: /api/parsing/tender
      const encodedUrl = encodeURIComponent(url);
      const { data } = await axios.get(`/api/parsing/tender?url=${encodedUrl}`);
      
      // Python Parsing service returns a structured object
      return {
        success: true,
        data: data
      };
    } catch (error: unknown) {
      console.error('Tender import error:', error);
      let errorMessage = 'Ошибка импорта данных';
      if (isAxiosError(error)) {
        errorMessage = error.response?.data?.detail || error.response?.data?.error || error.message || errorMessage;
      } else if (error instanceof Error) {
        errorMessage = error.message;
      }
      
      return { 
        success: false, 
        error: errorMessage 
      };
    }
  }
};
