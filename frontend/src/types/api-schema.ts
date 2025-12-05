import { z } from 'zod';

/**
 * Helper to coerce strings to numbers safely
 * Handles "123.45", 123.45, and "" -> 0
 */
const CoercedNumber = z.union([z.string(), z.number()])
  .transform((val) => {
    if (typeof val === 'number') return val;
    if (val.trim() === '') return 0;
    const parsed = parseFloat(val);
    return isNaN(parsed) ? 0 : parsed;
  });

/**
 * Schema for Invoice items coming from API
 * PHP backend might return mixed types
 */
export const ApiInvoiceItemSchema = z.object({
  id: z.string().optional(),
  description: z.string().optional(),
  type: z.string().optional(), // Legacy field often used as description
  model: z.string().optional(),
  name: z.string().optional(), // Legacy field
  quantity: CoercedNumber,
  price: CoercedNumber,
  reducerSpecs: z.any().optional(), // Too complex to strictly validate from legacy
}).transform((item) => ({
    // Normalize on the fly to match our internal structure
    id: item.id,
    description: item.description || item.type || '',
    model: item.model || (item.name !== 'Модель' ? item.name : '') || '',
    quantity: item.quantity,
    price: item.price,
    reducerSpecs: item.reducerSpecs || { stages: undefined, torqueNm: undefined },
}));

/**
 * Schema for the Invoice structure in the Registry list
 */
export const ApiRegistryInvoiceSchema = z.object({
  filename: z.string(),
  number: z.string().optional().default('N/A'),
  date: z.string().optional().default('N/A'), // We'll parse this later with utils.parseDate
  recipient: z.string().optional().default('N/A'),
  total: CoercedNumber,
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
  filename: z.string().optional(),
  number: z.string().optional(),
  date: z.string().optional(),
  validUntil: z.string().optional(),
  recipient: z.string().optional(),
  recipientINN: z.string().optional(),
  recipientAddress: z.string().optional(),
  currency: z.string().optional(),
  
  // Legacy contact fields
  contactPerson: z.string().optional(),
  contactPhone: z.string().optional(),
  contactEmail: z.string().optional(),
  position: z.string().optional(),
  contact: z.object({
      person: z.string().optional(),
      phone: z.string().optional(),
      email: z.string().optional(),
      position: z.string().optional(),
  }).optional(),

  // Legacy terms
  incoterm: z.string().optional(),
  deliveryPlace: z.string().optional(),
  deliveryTime: z.string().optional(),
  paymentTerms: z.string().optional(),
  warranty: z.string().optional(),
  commercialTerms: z.object({
      incoterm: z.string().optional(),
      deliveryPlace: z.string().optional(),
      deliveryTime: z.string().optional(),
      paymentTerms: z.string().optional(),
      warranty: z.string().optional(),
  }).optional(),

  items: z.array(z.any()).optional(), // We validate items separately or loosely here
  
  documentType: z.enum(['regular', 'tender']).optional(),
  tenderId: z.string().optional(),
  tenderPlatform: z.string().optional(),
  tenderLink: z.string().optional(),
  
  organizationId: z.string().optional(),
}).passthrough(); // Allow other fields

