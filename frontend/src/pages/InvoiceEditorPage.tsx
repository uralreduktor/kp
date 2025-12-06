import { invoiceService } from '@/features/invoice/service';
import { useEffect, useState } from 'react';
import { useForm, FormProvider, type SubmitHandler, type Resolver, type SubmitErrorHandler, type FieldErrors } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { invoiceSchema, type InvoiceFormData, type InvoiceItem } from '@/features/invoice/schema';
import { MainDataForm } from '@/features/invoice/components/MainDataForm';
import { ItemsEditor } from '@/features/invoice/components/ItemsEditor';
import { TermsForm } from '@/features/invoice/components/TermsForm';
import { FinalReview } from '@/features/invoice/components/FinalReview';
import { Stepper } from '@/features/invoice/components/Stepper';
import { PdfPreviewButton } from '@/features/invoice/components/PdfPreviewButton';
import { PdfPreviewPanel } from '@/features/invoice/components/PdfPreviewPanel';
import { useAutoSave } from '@/features/invoice/hooks/useAutoSave';

import { ChevronLeft, ChevronRight, Save, CheckCircle2, AlertCircle, Loader2, Edit, Eye, Download, X, LayoutGrid } from 'lucide-react';

const STEPS = ['Данные', 'Позиции', 'Условия', 'Готово'];

export default function InvoiceEditorPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [currentStep, setCurrentStep] = useState(0);
  const [isSaving, setIsSaving] = useState(false);
  const [savedFilename, setSavedFilename] = useState<string | null>(null);
  const [isSplitScreen, setIsSplitScreen] = useState(false);
  const [isPreviewFullscreen, setIsPreviewFullscreen] = useState(false);
  
  const filename = searchParams.get('filename');
  const documentTypeParam = searchParams.get('type') || 'regular';
  const editMode = searchParams.get('edit') === 'true';
  const isEditing = !!filename;
  const isViewMode = isEditing && !editMode; // Режим просмотра для существующих КП без параметра edit

  // Вычисление даты +30 дней
  const today = new Date();
  const validUntilDate = new Date(today);
  validUntilDate.setDate(today.getDate() + 30);
  const defaultValidUntil = validUntilDate.toISOString().split('T')[0];
  const defaultDate = today.toISOString().split('T')[0];

  const methods = useForm<InvoiceFormData>({
    resolver: zodResolver(invoiceSchema) as Resolver<InvoiceFormData>,
    defaultValues: {
      documentType: documentTypeParam === 'tender' ? 'tender' : 'regular',
      items: [],
      currency: 'Руб.',
      date: defaultDate,
      number: '',
      recipient: '',
      commercialTerms: {},
      contact: {},
      validUntil: defaultValidUntil, // Устанавливаем +30 дней по умолчанию
    },
    mode: 'onChange',
  });

  const { handleSubmit, reset, trigger, formState: { errors } } = methods;

  // Load data
  useEffect(() => {
    const loadData = async () => {
      if (isEditing && filename) {
        try {
          console.log('Loading invoice:', filename);
          const data = await invoiceService.load(filename);
          console.log('Loaded data:', data);
          reset(data);
          setSavedFilename(filename);
        } catch (error) {
          console.error('Error loading invoice:', error);
          alert('Ошибка загрузки КП: ' + (error instanceof Error ? error.message : 'Неизвестная ошибка'));
          navigate('/');
        }
      } else {
        // Load next number for new invoice
        const nextNumber = await invoiceService.getNextNumber();
        
        // Повторно вычисляем даты для сброса формы (на случай если компонент был смонтирован давно)
        const today = new Date();
        const validUntilDate = new Date(today);
        validUntilDate.setDate(today.getDate() + 30);
        
        reset({
          ...methods.getValues(),
          number: nextNumber,
          date: today.toISOString().split('T')[0],
          validUntil: validUntilDate.toISOString().split('T')[0],
          documentType: documentTypeParam === 'tender' ? 'tender' : 'regular',
        });
      }
    };
    loadData();
  }, [isEditing, filename, reset, navigate, documentTypeParam, methods]);

  const onSubmit: SubmitHandler<InvoiceFormData> = async (data) => {
    setIsSaving(true);
    try {
      const saveData: InvoiceFormData & { _filename?: string } = { ...data };
      if (savedFilename || filename) {
        saveData._filename = savedFilename || filename || undefined;
      }
      
      const result = await invoiceService.save(saveData);
      if (result.success) {
         setSavedFilename(result.filename);
         alert('КП успешно сохранено!');
         // Переход в режим просмотра сохраненного документа
         navigate(`/editor?filename=${encodeURIComponent(result.filename)}`);
      } else {
         throw new Error(result.error);
      }
    } catch (error) {
      console.error(error);
      alert('Ошибка сохранения: ' + (error instanceof Error ? error.message : 'Неизвестная ошибка'));
    } finally {
      setIsSaving(false);
    }
  };

  const onError: SubmitErrorHandler<InvoiceFormData> = (errors) => {
    console.error('Form validation errors:', errors);
    const errorMessages = Object.entries(errors)
      .map(([field, error]) => {
        // Перевод названий полей для пользователя
        const fieldNames: Record<string, string> = {
          number: 'Номер документа',
          date: 'Дата создания',
          recipient: 'Получатель',
          validUntil: 'Срок действия',
          'items': 'Позиции',
          'contact.person': 'Контактное лицо',
        };
        const fieldName = fieldNames[field] || field;

        // Детальный вывод ошибок для массива позиций
        if (field === 'items' && Array.isArray(error)) {
          const itemErrors = (error as FieldErrors<InvoiceItem>[]).map((itemErr, idx) => {
            if (!itemErr) return null;
            const details = Object.entries(itemErr)
              .map(([key, val]) => {
                const subFieldName = key === 'description' ? 'Описание' 
                                   : key === 'quantity' ? 'Количество' 
                                   : key === 'price' ? 'Цена' 
                                   : key;
                return `${subFieldName}: ${val?.message || 'Некорректно'}`;
              })
              .join(', ');
            return `  • Позиция ${idx + 1}: ${details}`;
          }).filter(Boolean).join('\n');
          
          return `- ${fieldName}:\n${itemErrors}`;
        }

        return `- ${fieldName}: ${error?.message || 'Ошибка валидации'}`;
      })
      .join('\n\n');
    
    alert(`Не удалось сохранить документ. Пожалуйста, исправьте следующие ошибки:\n\n${errorMessages}`);
  };

  const nextStep = async () => {
    if (currentStep >= STEPS.length - 1) return;

    let isValid = false;
    
    // Валидация полей текущего шага
    switch (currentStep) {
      case 0: // Шаг 1: Основные данные
        isValid = await trigger(['number', 'date', 'recipient']);
        break;
      case 1: { // Шаг 2: Позиции
        if (isViewMode) {
          isValid = true;
        } else {
          isValid = await trigger('items');
          // Дополнительная проверка: должна быть хотя бы одна позиция
          const items = methods.getValues('items');
          if (!items || items.length === 0) {
            alert('Добавьте хотя бы одну позицию товара');
            isValid = false;
          } else {
            // Проверяем каждую позицию на обязательные поля
            const itemsValid = items.every((item, index) => {
              if (!item.description || item.description.trim() === '') {
                trigger(`items.${index}.description`);
                return false;
              }
              if (item.quantity === undefined || item.quantity === null || item.quantity <= 0) {
                trigger(`items.${index}.quantity`);
                return false;
              }
              // Price allows 0, but not negative. NaN/undefined check
              if (item.price === undefined || item.price === null || item.price < 0) {
                trigger(`items.${index}.price`);
                return false;
              }
              return true;
            });
            isValid = isValid && itemsValid;
          }
        }
        break;
      }
      case 2: // Шаг 3: Условия (необязательные поля, пропускаем)
        isValid = true;
        break;
      default:
        isValid = true;
    }

    if (isValid && currentStep < STEPS.length - 1) {
      setCurrentStep((s: number) => s + 1);
    } else if (!isValid) {
      // Прокручиваем к первой ошибке
      const firstErrorField = Object.keys(errors)[0];
      if (firstErrorField) {
        const element = document.querySelector(`[name="${firstErrorField}"]`);
        if (element) {
          (element as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'center' });
          (element as HTMLElement).focus();
        }
      }
    }
  };

  const prevStep = () => {
    if (currentStep > 0) {
      setCurrentStep((s: number) => s - 1);
    }
  };

  // Внутренний компонент для автосохранения (должен быть внутри FormProvider)
  function AutoSaveIndicator({ filename, enabled }: { filename: string | null; enabled: boolean }) {
    const autoSaveStatus = useAutoSave({
      filename,
      enabled,
      interval: 30000,
    });

    if (autoSaveStatus.status === 'idle') return null;

    return (
      <div className="flex items-center gap-2 text-xs text-gray-600">
        {autoSaveStatus.status === 'saving' && (
          <>
            <Loader2 size={14} className="animate-spin text-blue-600" />
            <span className="hidden sm:inline">Автосохранение...</span>
          </>
        )}
        {autoSaveStatus.status === 'saved' && (
          <>
            <CheckCircle2 size={14} className="text-green-600" />
            <span className="hidden sm:inline">
              Сохранено {autoSaveStatus.lastSaved?.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}
            </span>
          </>
        )}
        {autoSaveStatus.status === 'error' && (
          <>
            <AlertCircle size={14} className="text-red-600" />
            <span className="hidden sm:inline text-red-600">Ошибка автосохранения</span>
          </>
        )}
      </div>
    );
  }

  const handleEditClick = () => {
    navigate(`/editor?filename=${encodeURIComponent(filename || '')}&edit=true`);
  };

  return (
    <div className={`min-h-screen bg-gray-50 ${isSplitScreen ? 'overflow-hidden' : ''}`}>
      <FormProvider {...methods}>
        <form onSubmit={handleSubmit(onSubmit, onError)} className="h-full">
          {/* Header */}
          <div className="bg-white shadow border-b border-gray-200 sticky top-0 z-40">
            <div className="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
              <div className="flex items-center gap-4">
                <button
                  type="button"
                  onClick={() => navigate('/')}
                  className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"
                >
                  <ChevronLeft size={24} />
                </button>
                <div>
                   <h1 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                     {isViewMode ? (
                       <>
                         <Eye size={18} className="text-gray-500" />
                         Просмотр КП
                       </>
                     ) : isEditing ? (
                       'Редактирование КП'
                     ) : (
                       'Создание КП'
                     )}
                     <span className="text-xs font-normal bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                       {methods.watch('number') || 'Новый'}
                     </span>
                   </h1>
                   <p className="text-xs text-gray-500">
                     {methods.watch('recipient') || 'Без получателя'}
                   </p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                {isViewMode ? (
                  <>
                    <a
                      href={`/api/invoices/${encodeURIComponent(filename)}/pdf`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium text-sm transition-colors"
                    >
                      <Download size={18} />
                      Скачать PDF
                    </a>
                    <button
                      type="button"
                      onClick={handleEditClick}
                      className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors shadow-sm"
                    >
                      <Edit size={18} />
                      Редактировать
                    </button>
                  </>
                ) : (
                  <>
                    <AutoSaveIndicator filename={savedFilename || filename} enabled={!isViewMode} />
                    {/* Кнопка переключения split-screen режима (только на десктопе) */}
                    <button
                      type="button"
                      onClick={() => setIsSplitScreen(!isSplitScreen)}
                      className={`hidden lg:flex items-center gap-2 px-4 py-2 rounded-lg font-medium text-sm transition-colors ${
                        isSplitScreen 
                          ? 'bg-blue-600 text-white hover:bg-blue-700' 
                          : 'text-gray-600 hover:bg-gray-100'
                      }`}
                      title={isSplitScreen ? 'Закрыть предпросмотр' : 'Открыть предпросмотр'}
                    >
                      <LayoutGrid size={18} />
                      {isSplitScreen ? 'Скрыть PDF' : 'Показать PDF'}
                    </button>
                    {/* Модальный предпросмотр для мобильных */}
                    <div className="lg:hidden">
                      <PdfPreviewButton />
                    </div>
                    <button
                      type="button"
                      onClick={() => {
                        if (confirm('Вы уверены, что хотите выйти без сохранения? Все несохраненные изменения будут потеряны.')) {
                          navigate('/');
                        }
                      }}
                      className="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-medium text-sm transition-colors shadow-sm"
                    >
                      <X size={18} />
                      Выйти без сохранения
                    </button>
                    <button
                      type="submit"
                      disabled={isSaving}
                      className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors shadow-sm disabled:opacity-70"
                    >
                      <Save size={18} />
                      {isSaving ? 'Сохранение...' : 'Сохранить'}
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className={`flex h-[calc(100vh-73px)] ${isSplitScreen ? 'lg:flex-row' : 'flex-col'}`}>
            {/* Form Section */}
            <div className={`flex-1 overflow-y-auto ${isSplitScreen ? 'lg:w-1/2' : 'w-full'} ${isSplitScreen ? '' : 'max-w-5xl mx-auto px-4 py-8'}`}>
              {!isSplitScreen && (
                <Stepper 
                  steps={STEPS} 
                  currentStep={currentStep} 
                  onStepClick={setCurrentStep} 
                  readOnly={isViewMode}
                />
              )}
              
              {isSplitScreen ? (
                <div className="h-full p-6">
                  {/* Step Content для split-screen */}
                  {currentStep === 0 && <MainDataForm readOnly={isViewMode} />}
                  {currentStep === 1 && <ItemsEditor readOnly={isViewMode} />}
                  {currentStep === 2 && <TermsForm readOnly={isViewMode} />}
                  {currentStep === 3 && <FinalReview />}
                </div>
              ) : (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[400px] p-6 mt-6">
                  {/* Step Content */}
                  {currentStep === 0 && <MainDataForm readOnly={isViewMode} />}
                  {currentStep === 1 && <ItemsEditor readOnly={isViewMode} />}
                  {currentStep === 2 && <TermsForm readOnly={isViewMode} />}
                  {currentStep === 3 && <FinalReview />}
                </div>
              )}

            {/* Navigation Footer */}
            {!isViewMode && (
              <div className="mt-6 flex items-center justify-between">
                 <button
                   type="button"
                   onClick={prevStep}
                   disabled={currentStep === 0}
                   className="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                 >
                   Назад
                 </button>

                 {currentStep < STEPS.length - 1 ? (
                   <button
                     type="button"
                     onClick={nextStep}
                     className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-colors flex items-center gap-2"
                   >
                     Далее
                     <ChevronRight size={18} />
                   </button>
                 ) : (
                   <button
                     type="submit"
                     className="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium shadow-sm transition-colors flex items-center gap-2"
                   >
                     <Save size={18} />
                     Завершить
                   </button>
                 )}
              </div>
            )}
            
              {isViewMode && !isSplitScreen && (
                <div className="mt-6 flex items-center justify-between">
                  <button
                    type="button"
                    onClick={prevStep}
                    disabled={currentStep === 0}
                    className="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    Назад
                  </button>

                  {currentStep < STEPS.length - 1 && (
                    <button
                      type="button"
                      onClick={nextStep}
                      className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-colors flex items-center gap-2"
                    >
                      Далее
                      <ChevronRight size={18} />
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* PDF Preview Panel (Split-screen) */}
            {isSplitScreen && (
              <PdfPreviewPanel
                isOpen={isSplitScreen}
                onClose={() => setIsSplitScreen(false)}
                onToggleFullscreen={() => setIsPreviewFullscreen(!isPreviewFullscreen)}
                isFullscreen={isPreviewFullscreen}
              />
            )}
          </div>
        </form>
      </FormProvider>
    </div>
  );
}
