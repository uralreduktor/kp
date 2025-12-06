import { z } from 'zod';

// Helper to handle PHP empty array [] instead of object {}
const phpArrayToObject = (val: unknown) => {
  if (Array.isArray(val) && val.length === 0) {
    return {};
  }
  return val;
};

// Helper for optional numbers that handles NaN from valueAsNumber: true
const optionalNumber = z.preprocess(
  (val) => (typeof val === 'number' && isNaN(val) ? undefined : val),
  z.number().optional()
) as z.ZodType<number | undefined>;

// Reducer Specs (Технические характеристики)
export const reducerSpecsSchema = z.object({
  type: z.string().optional(), // цилиндрический, червячный...
  stages: optionalNumber,
  torqueNm: optionalNumber,
  ratio: z.string().optional(), // Can be "31.5"
  housingMaterial: z.string().optional(),
  gearMaterial: z.string().optional(),
  bearings: z.array(z.string()).optional(),
  // Add other dynamic fields if needed as record
}).catchall(z.any());

// Invoice Item
export const invoiceItemSchema = z.object({
  id: z.string().uuid().optional(), // internal frontend id
  description: z.string().min(1, 'Описание обязательно'),
  model: z.string().optional(),
  quantity: z.number().min(1, 'Количество должно быть >= 1'),
  price: z.number().min(0, 'Цена не может быть отрицательной'),
  reducerSpecs: reducerSpecsSchema.optional(),
});

// Commercial Terms
export const commercialTermsSchema = z.object({
  incoterm: z.string().optional(),
  deliveryPlace: z.string().optional(),
  deliveryTime: z.string().optional(),
  paymentTerms: z.string().optional(),
  warranty: z.string().optional(),
});

// Contact Person
export const contactSchema = z.object({
  person: z.string().optional(),
  position: z.string().optional(),
  email: z.string().optional(),
  phone: z.string().optional(),
});

// Main Invoice Schema
export const invoiceSchema = z.object({
  filename: z.string().optional(), // For editing existing
  number: z.string().min(1, 'Номер обязателен'),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Дата должна быть в формате YYYY-MM-DD').min(1, 'Дата обязательна'),
  validUntil: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Дата должна быть в формате YYYY-MM-DD').optional().or(z.literal('')),
  
  recipient: z.string().min(1, 'Получатель обязателен'),
  recipientINN: z.string().optional(),
  recipientAddress: z.string().optional(),
  
  currency: z.string().default('Руб.'),
  
  items: z.array(invoiceItemSchema).default([]),
  
  commercialTerms: z.preprocess(phpArrayToObject, commercialTermsSchema).default({}),
  contact: z.preprocess(phpArrayToObject, contactSchema).default({}),
  
  organizationId: z.string().optional(),
  selectedBankId: z.string().optional(),
  documentType: z.enum(['regular', 'tender']).default('regular'),
  
  // Tender specific fields
  tenderId: z.string().optional(),
  tenderPlatform: z.string().optional(),
  tenderLink: z.string().optional(),
  
  // Technical Appendix Summary (Шапка технического приложения)
  technicalSummary: z.string().optional(),
});

export type InvoiceFormData = z.infer<typeof invoiceSchema>;
export type InvoiceItem = z.infer<typeof invoiceItemSchema>;
export type ReducerSpecs = z.infer<typeof reducerSpecsSchema>;

// Схемы валидации для каждого шага
export const step1Schema = z.object({
  number: z.string().min(1, 'Номер обязателен'),
  date: z.string().min(1, 'Дата обязательна'),
  recipient: z.string().min(1, 'Получатель обязателен'),
});

export const step2Schema = z.object({
  items: z.array(invoiceItemSchema).min(1, 'Добавьте хотя бы одну позицию'),
});

export const step3Schema = z.object({
  commercialTerms: commercialTermsSchema,
});
