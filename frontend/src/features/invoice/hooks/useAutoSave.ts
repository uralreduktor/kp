import { useEffect, useRef, useState } from 'react';
import { useFormContext } from 'react-hook-form';
import { invoiceService } from '../service';
import type { InvoiceFormData } from '../schema';

interface UseAutoSaveOptions {
  /** Интервал автосохранения в миллисекундах (по умолчанию 30 секунд) */
  interval?: number;
  /** Имя файла для сохранения (если редактируется существующий документ) */
  filename?: string | null;
  /** Включено ли автосохранение (по умолчанию true) */
  enabled?: boolean;
}

interface AutoSaveStatus {
  status: 'idle' | 'saving' | 'saved' | 'error';
  lastSaved: Date | null;
  error: string | null;
}

// Функция для нормализации данных перед сравнением
function normalizeDataForComparison(data: InvoiceFormData): string {
  // Создаем копию данных и нормализуем их
  const normalized = {
    number: String(data.number || '').trim(),
    date: data.date || '',
    validUntil: data.validUntil || '',
    recipient: String(data.recipient || '').trim(),
    recipientINN: String(data.recipientINN || '').trim(),
    recipientAddress: String(data.recipientAddress || '').trim(),
    currency: data.currency || 'Руб.',
    items: (data.items || []).map(item => ({
      id: item.id,
      description: String(item.description || '').trim(),
      model: String(item.model || '').trim(),
      quantity: Number(item.quantity) || 0,
      price: Number(item.price) || 0,
      reducerSpecs: item.reducerSpecs || { stages: undefined, torqueNm: undefined },
    })),
    commercialTerms: data.commercialTerms || {},
    contact: data.contact || {},
    organizationId: data.organizationId || '',
    documentType: data.documentType || 'regular',
    tenderId: data.tenderId || '',
    tenderPlatform: data.tenderPlatform || '',
    tenderLink: data.tenderLink || '',
  };
  
  // Сортируем items по id для стабильного сравнения
  normalized.items.sort((a, b) => (a.id || '').localeCompare(b.id || ''));
  
  return JSON.stringify(normalized);
}

export function useAutoSave({
  interval = 60000, // Увеличено до 60 секунд
  filename = null,
  enabled = true,
}: UseAutoSaveOptions = {}) {
  const { getValues, formState: { isDirty } } = useFormContext<InvoiceFormData>();
  const [status, setStatus] = useState<AutoSaveStatus>({
    status: 'idle',
    lastSaved: null,
    error: null,
  });
  const intervalRef = useRef<NodeJS.Timeout | null>(null);
  const lastSavedDataRef = useRef<string>('');
  const isSavingRef = useRef<boolean>(false);
  const isInitializedRef = useRef<boolean>(false);

  // Инициализация: сохраняем текущее состояние при первой загрузке
  useEffect(() => {
    if (!isInitializedRef.current && enabled) {
      const currentData = getValues();
      if (currentData.number && currentData.recipient) {
        lastSavedDataRef.current = normalizeDataForComparison(currentData);
        isInitializedRef.current = true;
        console.log('AutoSave: Initialized with current form data');
      }
    }
  }, [enabled, getValues]);

  useEffect(() => {
    if (!enabled) {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
      return;
    }

    // Функция сохранения
    const saveDraft = async () => {
      // Предотвращаем параллельные сохранения
      if (isSavingRef.current) {
        console.log('AutoSave: Already saving, skipping...');
        return;
      }

      try {
        // Проверяем, есть ли изменения в форме
        if (!isDirty) {
          console.log('AutoSave: Form is not dirty, skipping...');
          return;
        }

        const currentData = getValues();
        
        // Нормализуем данные для сравнения
        const normalizedDataString = normalizeDataForComparison(currentData);
        
        // Проверяем, есть ли реальные изменения
        if (normalizedDataString === lastSavedDataRef.current) {
          console.log('AutoSave: No changes detected, skipping...');
          return; // Нет изменений, пропускаем сохранение
        }

        // Проверяем минимальные требования для сохранения
        if (!currentData.number || !currentData.recipient) {
          console.log('AutoSave: Missing required fields, skipping...');
          return; // Недостаточно данных для сохранения
        }

        isSavingRef.current = true;
        setStatus((prev) => ({ ...prev, status: 'saving', error: null }));

        // Подготавливаем данные для сохранения
        const saveData: InvoiceFormData & { _filename?: string } = {
          ...currentData,
        };

        // Если редактируем существующий файл, передаем имя файла
        if (filename) {
          saveData._filename = filename;
        }

        console.log('AutoSave: Saving draft...');
        const result = await invoiceService.save(saveData);

        if (result.success) {
          lastSavedDataRef.current = normalizedDataString;
          setStatus({
            status: 'saved',
            lastSaved: new Date(),
            error: null,
          });
          console.log('AutoSave: Draft saved successfully');

          // Через 3 секунды возвращаем статус в idle
          setTimeout(() => {
            setStatus((prev) => {
              if (prev.status === 'saved') {
                return { ...prev, status: 'idle' };
              }
              return prev;
            });
          }, 3000);
        } else {
          throw new Error(result.error || 'Ошибка сохранения');
        }
      } catch (error) {
        console.error('AutoSave error:', error);
        setStatus({
          status: 'error',
          lastSaved: null,
          error: error instanceof Error ? error.message : 'Ошибка автосохранения',
        });

        // Через 5 секунд возвращаем статус в idle
        setTimeout(() => {
          setStatus((prev) => {
            if (prev.status === 'error') {
              return { ...prev, status: 'idle', error: null };
            }
            return prev;
          });
        }, 5000);
      } finally {
        isSavingRef.current = false;
      }
    };

    // Устанавливаем интервал
    intervalRef.current = setInterval(saveDraft, interval);

    // Очистка при размонтировании
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [enabled, interval, filename, getValues, isDirty]);

  return status;
}

