import { useMutation, useQuery, useQueryClient } from 'react-query';
import { toast } from 'react-toastify';
import { API_ENDPOINTS } from './client/api-endpoints';
import { advertisementClient } from './client/advertisement';

export interface Advertisement {
  id: number;
  title: string;
  type: 'image' | 'video' | 'html';
  position: 'header' | 'sidebar' | 'footer' | 'between_products' | 'product_detail' | 'popup';
  media_url?: string | null;
  media_type?: string | null;
  width?: number | null;
  height?: number | null;
  html_code?: string | null;
  target_url?: string | null;
  open_in_new_tab: boolean;
  is_active: boolean;
  display_settings?: any;
  order: number;
  created_at: string;
  updated_at: string;
}

export interface AdvertisementPagination {
  data: Advertisement[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AdvertisementInput {
  title: string;
  type: 'image' | 'video' | 'html';
  position: string;
  media?: File | null;
  html_code?: string;
  target_url?: string;
  open_in_new_tab?: boolean;
  is_active?: boolean;
  order?: number;
  display_settings?: any;
}

// Fetch advertisements list
export const useAdvertisementsQuery = (options?: any) => {
  return useQuery<AdvertisementPagination, Error>(
    [API_ENDPOINTS.ADVERTISEMENTS, options],
    () => advertisementClient.all(options),
    {
      keepPreviousData: true,
    }
  );
};

// Fetch single advertisement
export const useAdvertisementQuery = (id: number) => {
  return useQuery<Advertisement, Error>(
    [API_ENDPOINTS.ADVERTISEMENTS, id],
    () => advertisementClient.get(id),
    {
      enabled: !!id,
    }
  );
};

// Fetch position dimensions
export const usePositionDimensionsQuery = () => {
  return useQuery(
    'position-dimensions',
    () => advertisementClient.getPositionDimensions()
  );
};

// Create advertisement
export const useCreateAdvertisementMutation = () => {
  const queryClient = useQueryClient();

  return useMutation(
    (input: AdvertisementInput) => advertisementClient.create(input),
    {
      onSuccess: () => {
        toast.success('تبلیغ با موفقیت ایجاد شد');
        queryClient.invalidateQueries(API_ENDPOINTS.ADVERTISEMENTS);
      },
      onError: (error: any) => {
        toast.error(error?.response?.data?.message || 'خطا در ایجاد تبلیغ');
      },
    }
  );
};

// Update advertisement
export const useUpdateAdvertisementMutation = () => {
  const queryClient = useQueryClient();

  return useMutation(
    ({ id, input }: { id: number; input: AdvertisementInput }) =>
      advertisementClient.update(id, input),
    {
      onSuccess: () => {
        toast.success('تبلیغ با موفقیت به‌روزرسانی شد');
        queryClient.invalidateQueries(API_ENDPOINTS.ADVERTISEMENTS);
      },
      onError: (error: any) => {
        toast.error(error?.response?.data?.message || 'خطا در به‌روزرسانی تبلیغ');
      },
    }
  );
};

// Delete advertisement
export const useDeleteAdvertisementMutation = () => {
  const queryClient = useQueryClient();

  return useMutation(
    (id: number) => advertisementClient.delete(id),
    {
      onSuccess: () => {
        toast.success('تبلیغ با موفقیت حذف شد');
        queryClient.invalidateQueries(API_ENDPOINTS.ADVERTISEMENTS);
      },
      onError: (error: any) => {
        toast.error(error?.response?.data?.message || 'خطا در حذف تبلیغ');
      },
    }
  );
};

// Toggle advertisement status
export const useToggleAdvertisementStatusMutation = () => {
  const queryClient = useQueryClient();

  return useMutation(
    (id: number) => advertisementClient.toggleStatus(id),
    {
      onSuccess: () => {
        toast.success('وضعیت تبلیغ تغییر کرد');
        queryClient.invalidateQueries(API_ENDPOINTS.ADVERTISEMENTS);
      },
      onError: (error: any) => {
        toast.error(error?.response?.data?.message || 'خطا در تغییر وضعیت');
      },
    }
  );
};
