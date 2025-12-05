import type { Invoice } from '@/types/invoice';
import { Download, Copy, Trash2 } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

interface RegistryTableProps {
  invoices: Invoice[];
  onCopy: (invoice: Invoice) => void;
  onDelete?: (invoice: Invoice) => void;
  canDelete?: boolean;
}

export function RegistryTable({ invoices, onCopy, onDelete, canDelete }: RegistryTableProps) {
  const navigate = useNavigate();

  const handleRowClick = (e: React.MouseEvent, invoice: Invoice) => {
    // Если клик был по ссылке или кнопке внутри строки, не вызываем общую навигацию
    if ((e.target as HTMLElement).closest('a, button')) {
      return;
    }
    navigate(`/editor?filename=${encodeURIComponent(invoice.filename)}`);
  };

  return (
    <div className="overflow-x-auto bg-white rounded-lg shadow border border-gray-200">
      <table className="w-full text-left text-sm text-gray-600">
        <thead className="bg-gray-50 text-xs uppercase font-medium text-gray-500">
          <tr>
            <th className="px-6 py-3">Номер</th>
            <th className="px-6 py-3">Дата</th>
            <th className="px-6 py-3">Контрагент</th>
            <th className="px-6 py-3 text-right">Сумма</th>
            <th className="px-6 py-3 text-center">Действия</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {invoices.length === 0 ? (
            <tr>
              <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                Нет данных для отображения
              </td>
            </tr>
          ) : (
            invoices.map((invoice) => (
              <tr 
                key={invoice.filename} 
                className="hover:bg-gray-50 transition-colors cursor-pointer group"
                onClick={(e) => handleRowClick(e, invoice)}
              >
                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                  <Link 
                    to={`/editor?filename=${encodeURIComponent(invoice.filename)}`}
                    className="text-blue-600 hover:text-blue-800 hover:underline"
                    onClick={(e) => e.stopPropagation()}
                  >
                    {invoice.number}
                  </Link>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                   <div className="font-medium text-gray-900">{invoice.date}</div>
                   {invoice.saved_at && (
                     <div className="text-xs text-gray-400">
                       {new Date(invoice.saved_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                     </div>
                   )}
                </td>
                <td className="px-6 py-4">
                   <div className="max-w-xs truncate" title={invoice.recipient}>
                     {invoice.recipient}
                   </div>
                </td>
                <td className="px-6 py-4 text-right whitespace-nowrap font-medium">
                  {invoice.total.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  <span className="ml-1 text-xs text-gray-500">{invoice.currency}</span>
                </td>
                <td className="px-6 py-4 text-center whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                  <div className="flex items-center justify-center gap-2">
                    <a 
                      href={`/api/generate_pdf.php?filename=${invoice.filename}`} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="p-1.5 text-purple-600 hover:bg-purple-50 rounded transition-colors"
                      title="Скачать PDF"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Download size={18} />
                    </a>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        onCopy(invoice);
                      }}
                      className="p-1.5 text-green-600 hover:bg-green-50 rounded transition-colors"
                      title="Копировать"
                    >
                      <Copy size={18} />
                    </button>
                    {canDelete && onDelete && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onDelete(invoice);
                        }}
                        className="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors"
                        title="Удалить"
                      >
                        <Trash2 size={18} />
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
