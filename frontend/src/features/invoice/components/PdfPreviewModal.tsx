import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X, Loader2 } from 'lucide-react';

interface PdfPreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  url: string | null;
  isLoading: boolean;
}

export function PdfPreviewModal({ isOpen, onClose, url, isLoading }: PdfPreviewModalProps) {
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
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-5xl h-[85vh] transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all flex flex-col">
                <div className="flex items-center justify-between p-4 border-b border-gray-200">
                  <Dialog.Title as="h3" className="text-lg font-medium leading-6 text-gray-900">
                    Предпросмотр КП
                  </Dialog.Title>
                  <button
                    onClick={onClose}
                    className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500"
                  >
                    <X size={24} />
                  </button>
                </div>

                <div className="flex-1 bg-gray-100 p-4 overflow-hidden relative">
                  {isLoading ? (
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                       <Loader2 size={48} className="animate-spin text-blue-600 mb-4" />
                       <p className="text-gray-600">Генерация PDF...</p>
                    </div>
                  ) : url ? (
                    <iframe
                      src={url}
                      className="w-full h-full rounded shadow-lg bg-white"
                      title="PDF Preview"
                    />
                  ) : (
                    <div className="flex items-center justify-center h-full text-gray-500">
                      Ошибка генерации или документ не готов
                    </div>
                  )}
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
}

