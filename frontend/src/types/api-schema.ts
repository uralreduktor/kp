import { z } from 'zod';

// Helper to handle empty array -> {} for legacy data
const phpArrayToObject = (val: unknown) => {
  if (Array.isArray(val) && val.length === 0) {
    return {};
  }
  return val;
};

/**
 * Schema for Invoice items coming from API
 */
export const ApiInvoiceItemSchema = z.object({
  id: z.string().nullable().optional(),
  description: z.string().optional(),
  type: z.string().optional(), // Legacy field often used as description
  model: z.string().optional(),
  name: z.string().optional(), // Legacy field
  quantity: z.number(),
  price: z.number(),
  reducerSpecs: z.record(z.string(), z.any()).optional(),
}).transform((item) => {
  const ensureId = (value?: unknown) => {
    if (typeof value === 'string' && value.trim() !== '') return value;
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  };

  return {
    id: ensureId(item.id),
    description: item.description || item.type || '',
    model: item.model || (item.name !== 'Модель' ? item.name : '') || '',
    quantity: item.quantity,
    price: item.price,
    reducerSpecs: item.reducerSpecs || { stages: undefined, torqueNm: undefined },
  };
});

/**
 * Schema for the Invoice structure in the Registry list
 */
export const ApiRegistryInvoiceSchema = z.object({
  filename: z.string(),
  number: z.string().optional().default('N/A'),
  date: z.string().optional().default('N/A'), // We'll parse this later with utils.parseDate
  recipient: z.string().optional().default('N/A'),
  total: z.number(),
  currency: z.string().optional().default('Руб.'),
  documentType: z.enum(['regular', 'tender']).optional().default('regular'),
  saved_at: z.string().optional(),
  organizationId: z.string().nullable().optional(),
});

export const ApiRegistryListSchema = z.object({
  success: z.boolean(),
  invoices: z.array(ApiRegistryInvoiceSchema).optional().default([]),
  error: z.string().optional(),
});

/**
 * Schema for Full Invoice Data (Load/Save)
 */
export const ApiFullInvoiceSchema = z.object({
  filename: z.string().nullable().optional(),
  number: z.string(),
  date: z.string(),
  validUntil: z.string().optional(),
  recipient: z.string().optional(),
  recipientINN: z.string().optional(),
  recipientAddress: z.string().optional(),
  currency: z.string().optional(),
  reducerSpecs: z.record(z.string(), z.any()).optional(),
  
  contact: z.preprocess(phpArrayToObject, z.object({
      person: z.string().optional(),
      phone: z.string().optional(),
      email: z.string().optional(),
      position: z.string().optional(),
  }).optional()),
  // Legacy contact fields still may arrive
  contactPerson: z.string().optional(),
  contactPhone: z.string().optional(),
  contactEmail: z.string().optional(),
  position: z.string().optional(),

  commercialTerms: z.preprocess(phpArrayToObject, z.object({
      incoterm: z.string().optional(),
      deliveryPlace: z.string().optional(),
      deliveryTime: z.string().optional(),
      paymentTerms: z.string().optional(),
      warranty: z.string().optional(),
  }).optional()),
  // Legacy flattened terms
  incoterm: z.string().optional(),
  deliveryPlace: z.string().optional(),
  deliveryTime: z.string().optional(),
  paymentTerms: z.string().optional(),
  warranty: z.string().optional(),

  items: z.array(ApiInvoiceItemSchema).optional().default([]),
  
  documentType: z.enum(['regular', 'tender']).optional(),
  tenderId: z.string().optional(),
  tenderPlatform: z.string().optional(),
  tenderLink: z.string().optional(),
  
  organizationId: z.string().nullable().optional(),
  selectedBankId: z.string().nullable().optional(),
  _metadata: z.record(z.string(), z.any()).optional(),
}).passthrough();

