import { useFieldArray, useFormContext } from 'react-hook-form';
import type { InvoiceFormData } from '@/features/invoice/schema';
import { Plus, Trash2, MoreHorizontal, ChevronDown, ChevronUp, GripVertical } from 'lucide-react';
import { Disclosure, Transition } from '@headlessui/react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { 
  REDUCER_TYPE_OPTIONS, 
  REDUCER_MATERIAL_OPTIONS, 
  GEAR_MATERIAL_OPTIONS, 
  BEARING_SUGGESTIONS,
  TECHNICAL_SUMMARY_OPTIONS
} from '@/features/invoice/constants';

// Компонент для сортируемого элемента
function SortableItem({ 
  id, 
  children,
}: { 
  id: string; 
  children: (dragHandleProps: Record<string, unknown>) => React.ReactNode;
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div ref={setNodeRef} style={style}>
      {children({ ...attributes, ...listeners })}
    </div>
  );
}

interface ItemsEditorProps {
  readOnly?: boolean;
}

export function ItemsEditor({ readOnly = false }: ItemsEditorProps) {
  const { register, control, watch, setValue, formState: { errors } } = useFormContext<InvoiceFormData>();
  const { fields, append, remove, move } = useFieldArray({
    control,
    name: 'items',
  });

  const currency = watch('currency');
  const items = watch('items');
  const totalSum = items?.reduce((sum, item) => {
    const itemTotal = (item.quantity || 0) * (item.price || 0);
    return sum + itemTotal;
  }, 0) || 0;

  // Round to 2 decimal places to avoid floating point errors
  const formattedTotal = Math.round(totalSum * 100) / 100;

  // Calculate VAT (20% included)
  // VAT = Total * 20 / 120
  const vatRate = 20;
  const vatAmount = Math.round((formattedTotal * vatRate / (100 + vatRate)) * 100) / 100;

  // Настройка сенсоров для drag-and-drop
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const addItem = () => {
    append({
      id: crypto.randomUUID(),
      description: 'Редуктор ...',
      quantity: 1,
      price: 0,
      reducerSpecs: {
        stages: undefined,
        torqueNm: undefined,
        type: '',
        ratio: '',
        housingMaterial: '',
        gearMaterial: '',
        bearings: [],
        additionalInfo: '',
      },
    });
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = fields.findIndex((field) => field.id === active.id);
      const newIndex = fields.findIndex((field) => field.id === over.id);
      move(oldIndex, newIndex);
    }
  };

  // Компонент для отображения одного item (используется в обоих режимах)
  const ItemContent = ({ index, dragHandleProps }: { index: number; dragHandleProps?: Record<string, unknown> }) => (
    <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
      {/* Datalists for autocomplete */}
      <datalist id="reducer-type-options">
        {REDUCER_TYPE_OPTIONS.map(opt => <option key={opt} value={opt} />)}
      </datalist>
      <datalist id="reducer-material-options">
        {REDUCER_MATERIAL_OPTIONS.map(opt => <option key={opt} value={opt} />)}
      </datalist>
      <datalist id="gear-material-options">
        {GEAR_MATERIAL_OPTIONS.map(opt => <option key={opt} value={opt} />)}
      </datalist>
      <datalist id="bearing-suggestions">
        {BEARING_SUGGESTIONS.map(opt => <option key={opt} value={opt} />)}
      </datalist>

      {/* Basic Item Info (Always Visible) */}
      <div className="p-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
        <div className="md:col-span-1 flex items-center justify-center gap-1">
          {!readOnly && dragHandleProps && (
            <button
              type="button"
              {...dragHandleProps}
              className="p-1 text-gray-400 hover:text-gray-600 cursor-grab active:cursor-grabbing"
              title="Перетащить для изменения порядка"
            >
              <GripVertical size={18} />
            </button>
          )}
          <span className="font-bold text-gray-400">{index + 1}</span>
        </div>
        
        <div className="md:col-span-5 space-y-2">
          <label className="block text-xs font-medium text-gray-500 md:hidden">Описание</label>
          <textarea
            {...register(`items.${index}.description` as const)}
            disabled={readOnly}
            readOnly={readOnly}
            className={`block w-full rounded-md shadow-sm focus:ring-blue-500 sm:text-sm p-2 border min-h-10 disabled:bg-gray-50 disabled:text-gray-500 ${
              errors.items?.[index]?.description 
                ? 'border-red-500 focus:border-red-500' 
                : 'border-gray-300 focus:border-blue-500'
            }`}
            placeholder="Описание товара/услуги"
            rows={2}
          />
          {errors.items?.[index]?.description && (
            <p className="text-xs text-red-500">{errors.items[index]?.description?.message}</p>
          )}
        </div>
        
        <div className="md:col-span-2 space-y-2">
          <label className="block text-xs font-medium text-gray-500 md:hidden">Модель</label>
          <input
            type="text"
            {...register(`items.${index}.model` as const)}
            disabled={readOnly}
            readOnly={readOnly}
            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
            placeholder="РЦ-400..."
          />
        </div>

        <div className="md:col-span-3 grid grid-cols-2 gap-2">
          <div className="space-y-2">
            <label className="block text-xs font-medium text-gray-500 md:hidden">Кол-во</label>
            <input
              type="number"
              {...register(`items.${index}.quantity` as const, { valueAsNumber: true })}
              disabled={readOnly}
              readOnly={readOnly}
              className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
            />
          </div>
          <div className="space-y-2">
            <label className="block text-xs font-medium text-gray-500 md:hidden">Цена</label>
            <input
              type="number"
              {...register(`items.${index}.price` as const, { valueAsNumber: true })}
              disabled={readOnly}
              readOnly={readOnly}
              className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border disabled:bg-gray-50 disabled:text-gray-500"
            />
          </div>
          <div className="col-span-2 text-right text-sm font-bold text-gray-700 pt-1">
            = {(Math.round(((watch(`items.${index}.quantity`) || 0) * (watch(`items.${index}.price`) || 0)) * 100) / 100).toLocaleString()} {currency}
          </div>
        </div>

        <div className="md:col-span-1 flex justify-end pt-1">
          {!readOnly && (
            <button
              type="button"
              onClick={() => remove(index)}
              className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
              title="Удалить"
            >
              <Trash2 size={18} />
            </button>
          )}
        </div>
      </div>

      {/* Specs Expander */}
      {readOnly ? (
        // В режиме просмотра всегда показываем характеристики открытыми
        <div className="bg-gray-50 border-t border-gray-200">
          <div className="px-4 py-2 text-sm font-medium text-gray-700 flex items-center gap-2">
            <MoreHorizontal size={16} />
            Технические характеристики
          </div>
          <div className="px-4 pt-4 pb-6 bg-white border-t border-gray-100">
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Тип редуктора</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.type`) || watch(`items.${index}.reducerSpecs.customType`) || '-'}
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Ступени</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.stages`) || '-'}
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Крутящий момент (Нм)</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.torqueNm`) || '-'}
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Передаточное число</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.ratio`) || '-'}
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Материал корпуса</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.housingMaterial`) || '-'}
                </div>
                {watch(`items.${index}.reducerSpecs.housingMaterialNote`) && (
                  <div className="text-xs text-gray-500 mt-1">
                    {watch(`items.${index}.reducerSpecs.housingMaterialNote`)}
                  </div>
                )}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Материал шестерен</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {watch(`items.${index}.reducerSpecs.gearMaterial`) || '-'}
                </div>
                {watch(`items.${index}.reducerSpecs.gearMaterialNote`) && (
                  <div className="text-xs text-gray-500 mt-1">
                    {watch(`items.${index}.reducerSpecs.gearMaterialNote`)}
                  </div>
                )}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Подшипники</label>
                <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                  {(() => {
                    const bearings = watch(`items.${index}.reducerSpecs.bearings`);
                    return Array.isArray(bearings) 
                      ? bearings.filter((b: string) => b && b.trim()).join(', ') || '-'
                      : bearings || '-';
                  })()}
                </div>
              </div>
              {watch(`items.${index}.reducerSpecs.additionalInfo`) && (
                <div className="col-span-full">
                  <label className="block text-xs font-medium text-gray-500 mb-1">Дополнительная информация</label>
                  <div className="block w-full rounded border border-gray-200 text-sm py-1.5 px-2 bg-gray-50 text-gray-700 min-h-8">
                    {watch(`items.${index}.reducerSpecs.additionalInfo`)}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      ) : (
        <Disclosure>
          {({ open }) => (
            <>
              <Disclosure.Button className="flex w-full justify-between bg-gray-50 px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring focus-visible:ring-blue-500 focus-visible:ring-opacity-75">
                <span className="flex items-center gap-2">
                  <MoreHorizontal size={16} />
                  Технические характеристики
                </span>
                {open ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
              </Disclosure.Button>
            <Transition
              enter="transition duration-100 ease-out"
              enterFrom="transform scale-95 opacity-0"
              enterTo="transform scale-100 opacity-100"
              leave="transition duration-75 ease-out"
              leaveFrom="transform scale-100 opacity-100"
              leaveTo="transform scale-95 opacity-0"
            >
              <Disclosure.Panel className="px-4 pt-4 pb-6 bg-white border-t border-gray-100">
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Тип редуктора</label>
                    <input 
                      type="text"
                      list="reducer-type-options"
                      {...register(`items.${index}.reducerSpecs.type` as const)}
                      disabled={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="Выберите или введите..."
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Ступени</label>
                    <input 
                      type="number" 
                      {...register(`items.${index}.reducerSpecs.stages` as const, { valueAsNumber: true })}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="1-6"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Крутящий момент (Нм)</label>
                    <input 
                      type="number" 
                      {...register(`items.${index}.reducerSpecs.torqueNm` as const, { valueAsNumber: true })}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Передаточное число</label>
                    <input 
                      type="text" 
                      {...register(`items.${index}.reducerSpecs.ratio` as const)}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="31.5"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Материал корпуса</label>
                    <input 
                      type="text" 
                      list="reducer-material-options"
                      {...register(`items.${index}.reducerSpecs.housingMaterial` as const)}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="Чугун"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Материал шестерен</label>
                    <input 
                      type="text" 
                      list="gear-material-options"
                      {...register(`items.${index}.reducerSpecs.gearMaterial` as const)}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="Сталь..."
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">Подшипники</label>
                    <input 
                      type="text" 
                      list="bearing-suggestions"
                      value={(() => {
                        const bearings = watch(`items.${index}.reducerSpecs.bearings`);
                        return Array.isArray(bearings)
                          ? bearings.filter((b: string) => b && b.trim()).join(', ')
                          : bearings || '';
                      })()}
                      onChange={(e) => {
                        const value = e.target.value;
                        // Преобразуем строку в массив, разделяя по запятой
                        const bearingsArray = value.split(',').map(b => b.trim()).filter(b => b.length > 0);
                        setValue(`items.${index}.reducerSpecs.bearings`, bearingsArray.length > 0 ? bearingsArray : undefined);
                      }}
                      disabled={readOnly}
                      readOnly={readOnly}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="РФ ГОСТ, импортные..."
                    />
                  </div>
                  <div className="col-span-full">
                    <label className="block text-xs font-medium text-gray-500 mb-1">Дополнительная информация</label>
                    <textarea 
                      {...register(`items.${index}.reducerSpecs.additionalInfo` as const)}
                      disabled={readOnly}
                      readOnly={readOnly}
                      rows={3}
                      className="block w-full rounded border-gray-300 text-sm py-1.5 disabled:bg-gray-50 disabled:text-gray-500"
                      placeholder="Дополнительные технические характеристики..."
                    />
                  </div>
                </div>
              </Disclosure.Panel>
            </Transition>
          </>
        )}
        </Disclosure>
      )}
    </div>
  );

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h2 className="text-lg font-semibold text-gray-800">Позиции товаров</h2>
        {!readOnly && (
          <button
            type="button"
            onClick={addItem}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm text-sm font-medium flex items-center gap-2 transition-colors"
          >
            <Plus size={18} />
            Добавить позицию
          </button>
        )}
      </div>
      
      {/* Technical Summary Section */}
      {!readOnly && (
        <div className="bg-blue-50 p-4 rounded-lg border border-blue-200 mb-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-3">
            <h3 className="text-sm font-semibold text-blue-900">Вводная часть технического приложения</h3>
            <select
               className="text-xs border-blue-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 p-1.5 bg-white text-gray-700"
               onChange={(e) => {
                 const template = TECHNICAL_SUMMARY_OPTIONS.find(t => t.value === e.target.value);
                 if (template) {
                   setValue('technicalSummary', template.template, { shouldDirty: true });
                 }
               }}
               defaultValue=""
             >
               <option value="" disabled>Выберите шаблон текста...</option>
               {TECHNICAL_SUMMARY_OPTIONS.map(t => (
                 <option key={t.value} value={t.value}>{t.label}</option>
               ))}
            </select>
          </div>
          <textarea
            {...register('technicalSummary')}
            rows={3}
            className="block w-full rounded-md border-blue-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"
            placeholder="Технические параметры и комплектация оборудования соответствуют требованиям заказчика..."
          />
        </div>
      )}
      {readOnly && watch('technicalSummary') && (
        <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
           <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Техническое резюме</h3>
           <p className="text-sm text-gray-700 whitespace-pre-wrap">{watch('technicalSummary')}</p>
        </div>
      )}

      {fields.length === 0 && (
        <div className="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
          <p className="text-gray-500 mb-4">Список позиций пуст</p>
          {!readOnly && (
            <button
              type="button"
              onClick={addItem}
              className="text-blue-600 hover:text-blue-800 font-medium"
            >
              Добавить первую позицию
            </button>
          )}
        </div>
      )}

      {readOnly ? (
        <div className="space-y-4">
          {fields.map((field, index) => (
            <ItemContent key={field.id} index={index} />
          ))}
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={handleDragEnd}
        >
          <SortableContext items={fields.map((f) => f.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-4">
              {fields.map((field, index) => (
                <SortableItem key={field.id} id={field.id}>
                  {(dragHandleProps) => (
                    <ItemContent index={index} dragHandleProps={dragHandleProps} />
                  )}
                </SortableItem>
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}

      {fields.length > 0 && (
        <div className="bg-gray-100 p-4 rounded-lg flex flex-col items-end gap-1">
           <div className="flex justify-between items-center w-full md:w-1/2 lg:w-1/3">
             <span className="text-lg font-semibold text-gray-700">ИТОГО:</span>
             <span className="text-2xl font-bold text-gray-900">
               {formattedTotal.toLocaleString()} {currency}
             </span>
           </div>
           <div className="flex justify-between items-center w-full md:w-1/2 lg:w-1/3 text-sm text-gray-500">
             <span>В том числе НДС ({vatRate}%):</span>
             <span>{vatAmount.toLocaleString()} {currency}</span>
           </div>
        </div>
      )}
    </div>
  );
}
