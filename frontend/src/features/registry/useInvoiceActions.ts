import { useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import { useAuth } from '@/features/auth/AuthContext';

interface DeletePayload {
  filename: string;
}

interface CopyPayload {
  filename: string;
  documentType: 'regular' | 'tender';
}

export function useInvoiceActions() {
  const queryClient = useQueryClient();
  const { user } = useAuth();

  const deleteInvoice = useMutation({
    mutationFn: async ({ filename }: DeletePayload) => {
      const { data } = await axios.post('/api/delete.php', { 
        filename,
        user_email: user?.email || null,
      }, {
        withCredentials: true, // Важно для передачи cookies
      });
      if (!data.success) {
        throw new Error(data.error || data.message || 'Ошибка удаления');
      }
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
    },
  });

  const copyInvoice = useMutation({
    mutationFn: async ({ filename, documentType }: CopyPayload) => {
      const { data } = await axios.post('/api/copy.php', { filename, documentType });
      if (!data.success) {
        throw new Error(data.error || 'Ошибка копирования');
      }
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
    },
  });

  return {
    deleteInvoice,
    copyInvoice,
  };
}
