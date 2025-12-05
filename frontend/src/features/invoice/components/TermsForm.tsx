import { useFormContext } from 'react-hook-form';
import type { InvoiceFormData } from '@/features/invoice/schema';
import { Truck, Clock, CreditCard, ShieldCheck } from 'lucide-react';
import { 
  INCOTERM_OPTIONS, 
  PAYMENT_TEMPLATES, 
  DELIVERY_TIME_TEMPLATES, 
  WARRANTY_TEMPLATES 
} from '@/features/invoice/constants';

interface TermsFormProps {
  readOnly?: boolean;
}

export function TermsForm({ readOnly = false }: TermsFormProps) {
  const { register } = useFormContext<InvoiceFormData>();

  return (
    <div className="space-y-6">
      <h2 className="text-lg font-semibold text-gray-800 mb-4">Коммерческие условия</h2>
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Delivery */}
        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-blue-700 font-medium">
            <Truck size={20} />
            <h3>Условия поставки</h3>
          </div>
          
          <div className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Базис поставки (Incoterms)</label>
              <input
                type="text"
                {...register('commercialTerms.incoterm')}
                disabled={readOnly}
                readOnly={readOnly}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
                placeholder="EXW, DDP, CPT или другой базис"
                list="incoterms-list"
              />
              <datalist id="incoterms-list">
                {INCOTERM_OPTIONS.map((opt) => (
                  <option key={opt.code} value={opt.code}>{opt.label}</option>
                ))}
              </datalist>
              <p className="text-xs text-gray-500 mt-1">Можно выбрать из списка или ввести свой вариант</p>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Место отгрузки / доставки</label>
              <input
                type="text"
                {...register('commercialTerms.deliveryPlace')}
                disabled={readOnly}
                readOnly={readOnly}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
                placeholder="г. Екатеринбург, ул. Монтажников 4"
              />
            </div>
          </div>
        </div>

        {/* Time */}
        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-blue-700 font-medium">
            <Clock size={20} />
            <h3>Сроки</h3>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Срок поставки</label>
            <div className="relative">
               <input
                type="text"
                {...register('commercialTerms.deliveryTime')}
                disabled={readOnly}
                readOnly={readOnly}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
                placeholder="45-60 дней"
                list="delivery-terms"
              />
              <datalist id="delivery-terms">
                {DELIVERY_TIME_TEMPLATES.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </datalist>
            </div>
            <p className="text-xs text-gray-500 mt-1">Можно выбрать из списка или ввести свой вариант</p>
          </div>
        </div>

        {/* Payment */}
        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-blue-700 font-medium">
            <CreditCard size={20} />
            <h3>Оплата</h3>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Условия оплаты</label>
             <input
                type="text"
                {...register('commercialTerms.paymentTerms')}
                disabled={readOnly}
                readOnly={readOnly}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
                placeholder="30% аванс, 70% перед отгрузкой"
                list="payment-terms"
              />
              <datalist id="payment-terms">
                {PAYMENT_TEMPLATES.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </datalist>
          </div>
        </div>

        {/* Warranty */}
        <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm space-y-4">
          <div className="flex items-center gap-2 text-blue-700 font-medium">
            <ShieldCheck size={20} />
            <h3>Гарантия</h3>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Гарантийный срок</label>
             <input
                type="text"
                {...register('commercialTerms.warranty')}
                disabled={readOnly}
                readOnly={readOnly}
                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
                placeholder="12 месяцев"
                list="warranty-terms"
              />
              <datalist id="warranty-terms">
                {WARRANTY_TEMPLATES.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </datalist>
          </div>
        </div>
      </div>
    </div>
  );
}


