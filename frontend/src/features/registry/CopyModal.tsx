import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { FileText, Gavel, Copy } from 'lucide-react';

interface CopyModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (type: 'regular' | 'tender') => void;
  isPending: boolean;
}

export function CopyModal({ isOpen, onClose, onConfirm, isPending }: CopyModalProps) {
  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black/25 backdrop-blur-sm" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all">
                <div className="bg-gradient-to-r from-blue-50 to-blue-100 p-6 border-b">
                  <Dialog.Title as="h3" className="text-lg font-medium leading-6 text-blue-900 flex items-center gap-2">
                    <Copy size={20} />
                    Копирование КП
                  </Dialog.Title>
                </div>

                <div className="p-6">
                  <p className="text-gray-600 mb-6">
                    Выберите реестр, в который вы хотите скопировать этот документ:
                  </p>

                  <div className="space-y-3">
                    <button
                      onClick={() => onConfirm('regular')}
                      disabled={isPending}
                      className="w-full p-4 flex items-center justify-between rounded-xl border-2 border-gray-100 hover:border-blue-500 hover:bg-blue-50 transition-all group text-left"
                    >
                      <div className="flex items-center gap-4">
                        <div className="p-3 bg-blue-100 text-blue-600 rounded-full group-hover:bg-blue-600 group-hover:text-white transition-colors">
                          <FileText size={24} />
                        </div>
                        <div>
                          <div className="font-medium text-gray-900">Реестр КП</div>
                          <div className="text-sm text-gray-500">Обычные коммерческие предложения</div>
                        </div>
                      </div>
                    </button>

                    <button
                      onClick={() => onConfirm('tender')}
                      disabled={isPending}
                      className="w-full p-4 flex items-center justify-between rounded-xl border-2 border-gray-100 hover:border-purple-500 hover:bg-purple-50 transition-all group text-left"
                    >
                      <div className="flex items-center gap-4">
                        <div className="p-3 bg-purple-100 text-purple-600 rounded-full group-hover:bg-purple-600 group-hover:text-white transition-colors">
                          <Gavel size={24} />
                        </div>
                        <div>
                          <div className="font-medium text-gray-900">Реестр Тендеров</div>
                          <div className="text-sm text-gray-500">Специальные формы для торгов</div>
                        </div>
                      </div>
                    </button>
                  </div>

                  <div className="mt-6 flex justify-end">
                    <button
                      type="button"
                      className="inline-flex justify-center rounded-md border border-transparent bg-gray-100 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-offset-2"
                      onClick={onClose}
                    >
                      Отмена
                    </button>
                  </div>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
}

