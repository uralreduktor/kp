import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import { ApiRegistryListSchema } from '@/types/api-schema';

export function useInvoices() {
  return useQuery({
    queryKey: ['invoices'],
    queryFn: async () => {
      const { data } = await axios.get('/api/list.php');
      
      // 1. Runtime Validation via Zod
      const parsed = ApiRegistryListSchema.safeParse(data);
      
      if (!parsed.success) {
        console.error('API Validation Error:', parsed.error);
        throw new Error('Ошибка формата данных от сервера');
      }
      
      const response = parsed.data;

      if (!response.success) {
        throw new Error(response.error || 'Не удалось загрузить список КП');
      }
      return response.invoices;
    },
  });
}

