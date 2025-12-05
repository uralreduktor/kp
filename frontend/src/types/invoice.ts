export interface Invoice {
  filename: string;
  number: string;
  date: string;
  recipient: string;
  total: number;
  currency: string;
  documentType?: 'regular' | 'tender';
  saved_at?: string;
  organizationId?: string | null;
}

export interface InvoiceListResponse {
  success: boolean;
  invoices: Invoice[];
  error?: string;
}


