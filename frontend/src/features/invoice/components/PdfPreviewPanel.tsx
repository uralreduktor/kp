import { useEffect, useState, useRef } from 'react';
import { useFormContext } from 'react-hook-form';
import { invoiceService } from '@/features/invoice/service';
import { Loader2, X, Maximize2, Minimize2 } from 'lucide-react';
import type { InvoiceFormData } from '@/features/invoice/schema';

interface PdfPreviewPanelProps {
  isOpen: boolean;
  onClose: () => void;
  onToggleFullscreen?: () => void;
  isFullscreen?: boolean;
}

export function PdfPreviewPanel({ 
  isOpen, 
  onClose, 
  onToggleFullscreen,
  isFullscreen = false 
}: PdfPreviewPanelProps) {
  const { watch } = useFormContext<InvoiceFormData>();
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [prettyPrint, setPrettyPrint] = useState(false);

  const lastPreviewDataRef = useRef<string>('');
  const previewTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Автоматически обновляем превью при изменении данных формы
  useEffect(() => {
    if (!isOpen) {
      setPreviewUrl(null);
      lastPreviewDataRef.current = '';
      if (previewTimeoutRef.current) {
        clearTimeout(previewTimeoutRef.current);
        previewTimeoutRef.current = null;
      }
      return;
    }

    const updatePreview = async () => {
      setIsLoading(true);
      setError(null);

      try {
        const data = watch();
        
        // Нормализуем данные для сравнения
        const normalizedData = JSON.stringify({
          number: data.number?.trim() || '',
          recipient: data.recipient?.trim() || '',
          items: (data.items || []).map(item => ({
            description: item.description?.trim() || '',
            quantity: Number(item.quantity) || 0,
            price: Number(item.price) || 0,
          })),
        });
        
        // Проверяем, есть ли реальные изменения
        if (normalizedData === lastPreviewDataRef.current && previewUrl) {
          console.log('Preview: No changes detected, skipping update');
          setIsLoading(false);
          return;
        }
        
        // Проверяем минимальные требования для сохранения
        if (!data.number || !data.recipient) {
          setError('Заполните обязательные поля (номер и получатель)');
          setIsLoading(false);
          return;
        }
        
        // Сохраняем во временный файл для генерации PDF
        // Используем _filename для указания имени файла для перезаписи
        const tempFilename = `temp_preview_${Date.now()}.json`;
        const saveData = { ...data, _filename: tempFilename };
        
        console.log('Preview: Saving temp file:', tempFilename);
        const saveResult = await invoiceService.save(saveData);
        
        if (!saveResult.success) {
          throw new Error(saveResult.error || 'Ошибка сохранения временного файла');
        }
        
        // Используем имя файла, которое вернул сервер
        const actualFilename = saveResult.filename || tempFilename;
        console.log('Preview: Temp file saved:', actualFilename);
        
        // Обновляем ссылку на последние данные
        lastPreviewDataRef.current = normalizedData;
        
        // Небольшая задержка для гарантии, что файл записан на диск
        await new Promise(resolve => setTimeout(resolve, 300));
        
        const pdfUrl = `/api/v1/invoices/${encodeURIComponent(actualFilename)}/pdf`;
        console.log('Preview: Generating PDF from:', pdfUrl);
        setPreviewUrl(pdfUrl);
      } catch (err) {
        console.error('Preview error:', err);
        setError(err instanceof Error ? err.message : 'Ошибка генерации PDF');
      } finally {
        setIsLoading(false);
      }
    };

    // Debounce обновления превью (увеличено до 2 секунд)
    if (previewTimeoutRef.current) {
      clearTimeout(previewTimeoutRef.current);
    }
    previewTimeoutRef.current = setTimeout(updatePreview, 2000);
    
    return () => {
      if (previewTimeoutRef.current) {
        clearTimeout(previewTimeoutRef.current);
        previewTimeoutRef.current = null;
      }
    };
  }, [isOpen, watch, previewUrl]);

  if (!isOpen) return null;

  return (
    <div className={`bg-white border-l border-gray-200 flex flex-col h-full ${isFullscreen ? 'w-full' : 'w-1/2'}`}>
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
        <div className="flex items-center gap-3">
          <h3 className="text-sm font-medium text-gray-900">Предпросмотр КП</h3>
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={prettyPrint}
              onChange={(e) => setPrettyPrint(e.target.checked)}
              className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
            />
            <span className="text-xs text-gray-600">Pretty-print</span>
          </label>
        </div>
        <div className="flex items-center gap-2">
          {onToggleFullscreen && (
            <button
              onClick={onToggleFullscreen}
              className="p-1.5 hover:bg-gray-200 rounded transition-colors text-gray-600"
              title={isFullscreen ? 'Вернуть разделение' : 'На весь экран'}
            >
              {isFullscreen ? <Minimize2 size={18} /> : <Maximize2 size={18} />}
            </button>
          )}
          <button
            onClick={onClose}
            className="p-1.5 hover:bg-gray-200 rounded transition-colors text-gray-600"
            title="Закрыть предпросмотр"
          >
            <X size={18} />
          </button>
        </div>
      </div>

      {/* PDF Content */}
      <div className="flex-1 bg-gray-100 p-4 overflow-hidden relative">
        {isLoading ? (
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <Loader2 size={48} className="animate-spin text-blue-600 mb-4" />
            <p className="text-gray-600 text-sm">Генерация PDF...</p>
          </div>
        ) : error ? (
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="text-center">
              <p className="text-red-600 mb-2">{error}</p>
              <button
                onClick={() => {
                  setError(null);
                  setIsLoading(true);
                }}
                className="text-sm text-blue-600 hover:text-blue-700"
              >
                Попробовать снова
              </button>
            </div>
          </div>
        ) : previewUrl ? (
          <iframe
            src={`${previewUrl}${prettyPrint ? '&pretty=1' : ''}`}
            className="w-full h-full rounded shadow-lg bg-white"
            title="PDF Preview"
          />
        ) : (
          <div className="flex items-center justify-center h-full text-gray-500 text-sm">
            Заполните форму для предпросмотра
          </div>
        )}
      </div>
    </div>
  );
}

