import { useState, useEffect } from 'react';
import { useFormContext } from 'react-hook-form';
import type { InvoiceFormData } from '@/features/invoice/schema';
import { Calendar, Building2, User, Link, Download, Loader2, AlertCircle, Landmark } from 'lucide-react';
import { InnAutocomplete } from './InnAutocomplete';
import { AddressAutocomplete } from './AddressAutocomplete';
import { invoiceService } from '@/features/invoice/service';
import { CONTACT_TEMPLATES, TRADING_PLATFORMS, ORGANIZATIONS } from '@/features/invoice/constants';
import { detectTradingPlatform, formatBankDetails } from '@/features/invoice/utils';

interface MainDataFormProps {
  readOnly?: boolean;
}

export function MainDataForm({ readOnly = false }: MainDataFormProps) {
  const { register, formState: { errors }, watch, setValue } = useFormContext<InvoiceFormData>();
  const documentType = watch('documentType');
  const isTender = documentType === 'tender';
  
  const tenderLink = watch('tenderLink');
  const tenderPlatform = watch('tenderPlatform');
  const organizationId = watch('organizationId') || 'vector'; // Default to vector if not set
  
  // Detect trading platform when link changes
  useEffect(() => {
    if (tenderLink && !tenderPlatform) {
      const platform = detectTradingPlatform(tenderLink);
      if (platform) {
        setValue('tenderPlatform', platform.id, { shouldDirty: true });
      } else if (tenderLink.length > 10) {
        setValue('tenderPlatform', 'other', { shouldDirty: true });
      }
    }
  }, [tenderLink, tenderPlatform, setValue]);
  
  const [importUrl, setImportUrl] = useState('');
  const [isImporting, setIsImporting] = useState(false);
  const [importError, setImportError] = useState<string | null>(null);

  const handleImport = async () => {
    if (!importUrl) return;
    
    setIsImporting(true);
    setImportError(null);
    
    try {
      const result = await invoiceService.importTenderData(importUrl);
      
      if (result.success && result.data) {
        const data = result.data as {
          tenderNumber?: string;
          platform?: string;
          recipientINN?: string;
          recipient?: string;
          recipientAddress?: string;
          companySuggestions?: Array<{ name: string; inn?: string; matchScore: number; region?: string; address?: string }>;
          deliveryAddress?: string;
          deliveryIncoterm?: string;
          items?: Array<{ name: string; quantity: string | number }>;
        };
        
        console.log('📦 Import data:', data);
        console.log('📦 recipientINN:', data.recipientINN, 'type:', typeof data.recipientINN);
        
        // Auto-fill fields
        if (data.tenderNumber) setValue('tenderId', data.tenderNumber, { shouldDirty: true });
        if (data.platform) setValue('tenderPlatform', data.platform, { shouldDirty: true });
        setValue('tenderLink', importUrl, { shouldDirty: true });
        
        // Handle recipient logic with potential company suggestions
        if (data.recipientINN) {
          // Found INN directly (either on page or auto-matched via DaData)
          setValue('recipientINN', String(data.recipientINN), { shouldDirty: true, shouldValidate: true });
          if (data.recipient) {
            setValue('recipient', data.recipient, { shouldDirty: true, shouldValidate: true });
          }
          
          // If address is not provided, fetch it from DaData by INN
          if (data.recipientAddress) {
            setValue('recipientAddress', data.recipientAddress, { shouldDirty: true, shouldValidate: true });
          } else {
            // Automatically fetch company details by INN from DaData
            try {
              const companyDetails = await invoiceService.getCompanyByINN(String(data.recipientINN));
              if (companyDetails) {
                // Extract address from DaData response (same logic as InnAutocomplete)
                const address = (companyDetails as any).address || (companyDetails.data as any)?.address?.value || '';
                if (address) {
                  setValue('recipientAddress', address, { shouldDirty: true, shouldValidate: true });
                }
                // Also update recipient name if it's more accurate from DaData and wasn't already set
                if (!data.recipient && companyDetails.value) {
                  setValue('recipient', companyDetails.value, { shouldDirty: true, shouldValidate: true });
                }
              }
            } catch (error) {
              console.error('Failed to fetch company details from DaData:', error);
              // Continue without address - not critical
            }
          }
        } else if (data.companySuggestions && data.companySuggestions.length > 0) {
          // Found suggestions but no confident match
          const suggestions = data.companySuggestions;
          let message = 'Найдено несколько компаний с похожим названием.\n\n';
          message += 'Адрес поставки: ' + (data.deliveryAddress || 'не указан') + '\n\n';
          message += 'Выберите номер компании:\n\n';
          
          suggestions.slice(0, 5).forEach((company: { name: string; inn?: string; matchScore: number; region?: string; address?: string }, index: number) => {
            const matchInfo = company.matchScore >= 80 ? ' ⭐ (совпадение региона)' : '';
            message += `${index + 1}. ${company.name}\n`;
            message += `   ИНН: ${company.inn || 'н/д'}${matchInfo}\n`;
            message += `   ${company.region || company.address || ''}\n\n`;
          });
          
          message += '0 - Ввести ИНН вручную';
          
          // Using window.prompt to match legacy behavior as requested
          const choice = window.prompt(message, '1');
          
          if (choice && choice !== '0') {
            const selectedIndex = parseInt(choice) - 1;
            if (selectedIndex >= 0 && selectedIndex < suggestions.length) {
              const selected = suggestions[selectedIndex];
              setValue('recipientINN', selected.inn, { shouldDirty: true });
              setValue('recipientAddress', selected.address, { shouldDirty: true });
              setValue('recipient', selected.name, { shouldDirty: true });
            }
          } else {
             // User chose manual entry or cancelled
             if (data.recipient) setValue('recipient', data.recipient, { shouldDirty: true });
          }
        } else {
          // Fallback: just set what we found
          if (data.recipient) setValue('recipient', data.recipient, { shouldDirty: true });
          if (data.recipientAddress) setValue('recipientAddress', data.recipientAddress, { shouldDirty: true });
        }
        
        // Auto-fill Commercial Terms (Delivery Place & Incoterm)
        if (data.deliveryAddress) {
          setValue('commercialTerms.deliveryPlace', data.deliveryAddress, { shouldDirty: true });
        }
        if (data.deliveryIncoterm) {
          setValue('commercialTerms.incoterm', data.deliveryIncoterm, { shouldDirty: true });
        }

        // Map items if found
        if (data.items && Array.isArray(data.items)) {
          const mappedItems = data.items.map((item: { name: string; quantity: string | number }) => ({
            id: crypto.randomUUID(),
            description: item.name,
            quantity: Number(item.quantity) || 1,
            price: 0,
            reducerSpecs: {
              stages: undefined,
              torqueNm: undefined,
            },
            model: '',
          }));
          
          // We check if current items are empty to avoid overwriting existing work unexpectedly
          // Or just append? Let's replace for now or ask confirmation. 
          // Since this is "Import", let's assume we want to use this data.
          // But we are in MainDataForm, items are in Step 2.
          // We can set them here.
          const currentItems = watch('items');
          if (currentItems.length === 0 || confirm('Заменить текущие позиции товаров данными из тендера?')) {
             setValue('items', mappedItems, { shouldDirty: true });
          }
        }
        
        alert('Данные успешно импортированы!');
      } else {
        setImportError(result.error || 'Не удалось получить данные по ссылке');
      }
    } catch (err) {
      setImportError(err instanceof Error ? err.message : 'Ошибка импорта');
    } finally {
      setIsImporting(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Basic Info */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="space-y-2">
          <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Номер документа</label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 dark:text-slate-500">
              <span className="text-xs font-bold">#</span>
            </div>
            <input
              type="text"
              {...register('number')}
              disabled={readOnly}
              readOnly={readOnly}
              className={`pl-8 block w-full rounded-md shadow-sm focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500 ${
                errors.number 
                  ? 'border-red-500 focus:border-red-500' 
                  : 'border-gray-300 dark:border-slate-600 focus:border-blue-500'
              }`}
              placeholder="VEC-2025-..."
            />
          </div>
          {errors.number && <p className="text-xs text-red-500 mt-1">{errors.number.message}</p>}
        </div>

        <div className="space-y-2">
          <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Дата создания</label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 dark:text-slate-500">
              <Calendar size={16} />
            </div>
            <input
              type="date"
              {...register('date')}
              disabled={readOnly}
              readOnly={readOnly}
              className={`pl-10 block w-full rounded-md shadow-sm focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500 ${
                errors.date 
                  ? 'border-red-500 focus:border-red-500' 
                  : 'border-gray-300 dark:border-slate-600 focus:border-blue-500'
              }`}
            />
          </div>
          {errors.date && <p className="text-xs text-red-500 mt-1">{errors.date.message}</p>}
        </div>

        <div className="space-y-2">
          <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Срок действия до</label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 dark:text-slate-500">
              <Calendar size={16} />
            </div>
            <input
              type="date"
              {...register('validUntil')}
              disabled={readOnly}
              readOnly={readOnly}
              className="pl-10 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
            />
          </div>
        </div>
      </div>

      {/* Recipient Info */}
      <div className="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700 space-y-4">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
          <Building2 size={18} className="text-gray-500 dark:text-slate-400" />
          Данные получателя
        </h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Название организации</label>
            <input
              type="text"
              {...register('recipient')}
              disabled={readOnly}
              readOnly={readOnly}
              className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
              placeholder="ООО Ромашка"
            />
            {errors.recipient && <p className="text-xs text-red-500">{errors.recipient.message}</p>}
          </div>

          {readOnly ? (
            <>
              <div className="space-y-2">
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">ИНН</label>
                <div className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm sm:text-sm p-2 border bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-slate-100">
                  {watch('recipientINN') || '-'}
                </div>
              </div>
              <div className="col-span-1 md:col-span-2 space-y-2">
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Адрес доставки / Юридический адрес</label>
                <div className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm sm:text-sm p-2 border bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-slate-100">
                  {watch('recipientAddress') || '-'}
                </div>
              </div>
            </>
          ) : (
            <>
              <InnAutocomplete
                innFieldName="recipientINN"
                nameFieldName="recipient"
                addressFieldName="recipientAddress"
              />
              <div className="col-span-1 md:col-span-2">
                <AddressAutocomplete
                  fieldName="recipientAddress"
                  label="Адрес доставки / Юридический адрес"
                  placeholder="г. Екатеринбург, ул..."
                />
              </div>
            </>
          )}
        </div>
      </div>

      {/* Contact Person */}
      <div className="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700 space-y-4">
        <div className="flex justify-between items-center">
          <h3 className="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <User size={18} className="text-gray-500 dark:text-slate-400" />
            Контактное лицо
          </h3>
          
          {!readOnly && (
            <select
              className="text-xs border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1 bg-white dark:bg-slate-900 dark:text-slate-100"
              onChange={(e) => {
                const template = CONTACT_TEMPLATES.find(t => t.id === e.target.value);
                if (template) {
                  setValue('contact.person', template.person, { shouldDirty: true });
                  setValue('contact.position', template.position, { shouldDirty: true });
                }
              }}
              defaultValue=""
            >
              <option value="" disabled>Быстрый выбор...</option>
              {CONTACT_TEMPLATES.map(t => (
                <option key={t.id} value={t.id}>{t.person}</option>
              ))}
            </select>
          )}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">ФИО</label>
             <input
               type="text"
               {...register('contact.person')}
               disabled={readOnly}
               readOnly={readOnly}
               className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
               placeholder="Иванов Иван Иванович"
             />
          </div>
          <div className="space-y-2">
             <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Должность</label>
             <input
               type="text"
               {...register('contact.position')}
               disabled={readOnly}
               readOnly={readOnly}
               className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
               placeholder="Начальник отдела снабжения"
             />
          </div>
          <div className="space-y-2">
             <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Email</label>
             <input
               type="email"
               {...register('contact.email')}
               disabled={readOnly}
               readOnly={readOnly}
               className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
               placeholder="ivanov@example.com"
             />
          </div>
          <div className="space-y-2">
             <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Телефон</label>
             <input
               type="text"
               {...register('contact.phone')}
               disabled={readOnly}
               readOnly={readOnly}
               className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100 disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500"
               placeholder="+7 (999) ..."
             />
          </div>
        </div>
      </div>

      {/* Banking Details */}
      <div className="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700 space-y-4">
        <h3 className="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
          <Landmark size={18} className="text-gray-500 dark:text-slate-400" />
          Банковские реквизиты
        </h3>
        
        {/* Selector - only if multiple accounts */}
        {ORGANIZATIONS[organizationId as keyof typeof ORGANIZATIONS]?.bankAccounts?.length > 1 && (
          <div className="space-y-2 mb-4">
            <label className="block text-sm font-medium text-gray-700 dark:text-slate-200">Выберите банк для оплаты</label>
            {readOnly ? (
              <div className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm sm:text-sm p-2 border bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-slate-100">
                {ORGANIZATIONS[organizationId as keyof typeof ORGANIZATIONS]?.bankAccounts?.find(b => b.id === watch('selectedBankId'))?.name || 'По умолчанию'}
              </div>
            ) : (
              <select
                {...register('selectedBankId')}
                className="block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100"
              >
                {/* If nothing selected, logic will pick first. We can add empty option or select first by default */}
                {ORGANIZATIONS[organizationId as keyof typeof ORGANIZATIONS]?.bankAccounts.map(bank => (
                  <option key={bank.id} value={bank.id}>{bank.name}</option>
                ))}
              </select>
            )}
          </div>
        )}

        {/* Details Text (Always visible as preview) */}
        <div className="space-y-2">
          <label className="block text-xs font-medium text-gray-500 dark:text-slate-400">Реквизиты (предпросмотр)</label>
          <pre className="block w-full rounded-md border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 text-xs text-gray-700 dark:text-slate-100 whitespace-pre-wrap font-mono border">
            {formatBankDetails(organizationId, watch('selectedBankId'))}
          </pre>
        </div>
      </div>

      {/* Tender Specifics */}
      {isTender && (
         <div className="bg-blue-50 dark:bg-slate-800 p-4 rounded-lg border border-blue-200 dark:border-slate-700 space-y-4">
            <h3 className="text-sm font-semibold text-blue-900 dark:text-slate-100 flex items-center gap-2">
              <Link size={18} />
              Параметры тендера
            </h3>
            
            {/* Import Section */}
            {!readOnly && (
              <div className="mb-4 p-3 bg-white dark:bg-slate-900 rounded border border-blue-100 dark:border-slate-700 shadow-sm">
                <label className="block text-xs font-medium text-gray-500 dark:text-slate-300 mb-2">Импорт данных из ссылки (B2B-Center и др.)</label>
                <div className="flex gap-2">
                  <input
                    type="url"
                    value={importUrl}
                    onChange={(e) => setImportUrl(e.target.value)}
                    placeholder="https://www.b2b-center.ru/market/..."
                    className="flex-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100"
                  />
                  <button
                    type="button"
                    onClick={handleImport}
                    disabled={isImporting || !importUrl}
                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium transition-colors"
                  >
                    {isImporting ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />}
                    Загрузить
                  </button>
                </div>
                {importError && (
                  <div className="mt-2 text-xs text-red-600 flex items-center gap-1">
                    <AlertCircle size={12} />
                    {importError}
                  </div>
                )}
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
               <div className="space-y-2">
                  <label className="block text-sm font-medium text-blue-800 dark:text-slate-200">Номер закупки</label>
                  <input
                    type="text"
                    {...register('tenderId')}
                    disabled={readOnly}
                    readOnly={readOnly}
                    className="block w-full rounded-md border-blue-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:text-slate-100 dark:bg-slate-900"
                  />
               </div>
               <div className="space-y-2">
                  <label className="block text-sm font-medium text-blue-800 dark:text-slate-200">Торговая площадка</label>
                  {readOnly ? (
                    <div className="block w-full rounded-md border-blue-300 dark:border-slate-600 shadow-sm sm:text-sm p-2 border bg-blue-50 dark:bg-slate-800 text-gray-700 dark:text-slate-100">
                      {TRADING_PLATFORMS.find(p => p.id === watch('tenderPlatform'))?.name || watch('tenderPlatform') || '-'}
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <select
                        {...register('tenderPlatform')}
                        className="block w-full rounded-md border-blue-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-white dark:bg-slate-900 dark:text-slate-100"
                      >
                        <option value="">Выберите площадку...</option>
                        {TRADING_PLATFORMS.map(p => (
                          <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                      </select>
                      {/* If "other" is selected or value is not in list (custom), show input? 
                          Actually, if we use select with fixed values, we need 'other' option handling.
                          The select binds to tenderPlatform. If user selects 'other', we might want to show text input.
                          But the schema field is string. We can reuse the same field if we are clever, or use a helper state.
                          Let's assume if value is 'other', we show a text input for custom value.
                          But wait, if we set value to 'other', then it's 'other'. We need another field for custom name?
                          In torgikp.html: if val === 'other', it sets tradingPlatform = 'other'. And shows input.
                          Input writes to tradingPlatform? No.
                          "If platform is other, show input".
                          The input should probably update the same field, but that would hide the select?
                          Let's keep it simple: Select sets the ID. If ID is 'other', show another input that allows typing custom name.
                          But schema has only tenderPlatform.
                          If I type "My Platform", select value becomes "My Platform" which is not in list, so select shows "Select..."?
                          Let's use a ComboBox or just: Select (with 'other') + Input (if 'other' selected or value not in list).
                      */}
                      {(watch('tenderPlatform') === 'other' || (watch('tenderPlatform') && !TRADING_PLATFORMS.some(p => p.id === watch('tenderPlatform')))) && (
                         <input
                           type="text"
                           placeholder="Введите название площадки"
                           className="block w-full rounded-md border-blue-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border mt-2 bg-white dark:bg-slate-900 dark:text-slate-100"
                           onChange={(e) => setValue('tenderPlatform', e.target.value, { shouldDirty: true })}
                           // We don't bind value directly to avoid conflict with select if we want to switch back?
                           // Actually, if we type, value changes, so select might lose 'other'.
                           // But if value is not in list, select usually shows empty or first?
                           // Let's just use a text input if 'other' is selected, and maybe a button to clear?
                           // Or use the pattern from torgikp.html:
                           // select value is controlled.
                           // if val === 'other' -> set state 'other'.
                           defaultValue={watch('tenderPlatform') === 'other' ? '' : watch('tenderPlatform')}
                         />
                      )}
                    </div>
                  )}
               </div>
               <div className="col-span-1 md:col-span-2 space-y-2">
                  <label className="block text-sm font-medium text-blue-800 dark:text-slate-200">Ссылка на закупку</label>
                  <input
                    type="url"
                    {...register('tenderLink')}
                    disabled={readOnly}
                    readOnly={readOnly}
                    className="block w-full rounded-md border-blue-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 dark:disabled:bg-slate-800 disabled:text-gray-500 dark:text-slate-100 dark:bg-slate-900"
                  />
               </div>
            </div>
         </div>
      )}
    </div>
  );
}


