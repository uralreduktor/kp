import { useFormContext } from 'react-hook-form';
import type { InvoiceFormData } from '@/features/invoice/schema';
import { CheckCircle, AlertTriangle } from 'lucide-react';

export function FinalReview() {
  const { watch, formState: { errors } } = useFormContext<InvoiceFormData>();
  
  const data = watch();
  const hasErrors = Object.keys(errors).length > 0;

  return (
    <div className="space-y-6 text-center py-8">
      {hasErrors ? (
         <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-2xl mx-auto">
            <div className="flex flex-col items-center gap-4">
               <div className="p-3 bg-red-100 rounded-full text-red-600">
                 <AlertTriangle size={32} />
               </div>
               <div>
                 <h3 className="text-lg font-bold text-red-800">Обнаружены ошибки</h3>
                 <p className="text-red-600 mt-1">Пожалуйста, вернитесь и исправьте выделенные поля.</p>
               </div>
            </div>
         </div>
      ) : (
         <div className="bg-green-50 border border-green-200 rounded-lg p-6 max-w-2xl mx-auto">
            <div className="flex flex-col items-center gap-4">
               <div className="p-3 bg-green-100 rounded-full text-green-600">
                 <CheckCircle size={32} />
               </div>
               <div>
                 <h3 className="text-lg font-bold text-green-800">Все данные заполнены</h3>
                 <p className="text-green-700 mt-1">Документ готов к сохранению.</p>
                 <p className="text-sm text-green-600 mt-4">
                   Получатель: <strong>{data.recipient}</strong><br/>
                   Сумма: <strong>{data.items?.reduce((acc, i) => acc + (i.quantity * i.price), 0).toLocaleString()} {data.currency}</strong>
                 </p>
               </div>
            </div>
         </div>
      )}
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl mx-auto mt-8 text-left">
          <div className="bg-white p-4 rounded border border-gray-200">
             <h4 className="font-medium text-gray-500 text-xs uppercase mb-2">Тип документа</h4>
             <p>{data.documentType === 'tender' ? 'Тендерное КП' : 'Коммерческое предложение'}</p>
          </div>
          <div className="bg-white p-4 rounded border border-gray-200">
             <h4 className="font-medium text-gray-500 text-xs uppercase mb-2">Дата</h4>
             <p>{data.date}</p>
          </div>
      </div>
    </div>
  );
}


