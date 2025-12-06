import { useState } from 'react';
import { useFormContext } from 'react-hook-form';
import { invoiceService } from '@/features/invoice/service';
import { PdfPreviewModal } from '@/features/invoice/components/PdfPreviewModal';
import { FileText } from 'lucide-react';
import type { InvoiceFormData } from '@/features/invoice/schema';

export function PdfPreviewButton() {
  const { getValues } = useFormContext<InvoiceFormData>();
  const [isPreviewOpen, setIsPreviewOpen] = useState(false);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const handlePreview = async () => {
    setIsPreviewOpen(true);
    setIsLoading(true);
    setPreviewUrl(null);

    try {
      const data = getValues();
      
      // 1. Save current state to a temp file
      const tempFilename = `temp_preview_${Date.now()}.json`;
      // Use _filename to force overwrite/create specific file
      const saveData = { ...data, _filename: tempFilename };
      const result = await invoiceService.save(saveData);

      const actualFilename = result.filename || tempFilename;

      // 2. Generate PDF from this temp file
      // Using new Python API which expects filename
      
      const pdfUrl = `/api/invoices/${encodeURIComponent(actualFilename)}/pdf`;
      setPreviewUrl(pdfUrl);
    } catch (error) {
      console.error('Preview error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <button
        type="button"
        onClick={handlePreview}
        className="hidden sm:flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium text-sm transition-colors"
      >
        <FileText size={18} />
        Предпросмотр
      </button>

      <PdfPreviewModal
        isOpen={isPreviewOpen}
        onClose={() => setIsPreviewOpen(false)}
        url={previewUrl}
        isLoading={isLoading}
      />
    </>
  );
}
