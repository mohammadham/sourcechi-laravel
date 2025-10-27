import { API_ENDPOINTS } from './api-endpoints';
import { crudFactory } from './curd-factory';
import { HttpClient } from './http-client';
import { Advertisement, AdvertisementInput, AdvertisementPagination } from '@/data/advertisements';

export const advertisementClient = {
  ...crudFactory<Advertisement, any, AdvertisementInput>(
    API_ENDPOINTS.ADVERTISEMENTS
  ),
  
  // Get all advertisements with pagination
  all: (params?: any) => {
    return HttpClient.get<AdvertisementPagination>(API_ENDPOINTS.ADVERTISEMENTS, params);
  },
  
  // Get single advertisement
  get: (id: number) => {
    return HttpClient.get<Advertisement>(`${API_ENDPOINTS.ADVERTISEMENTS}/${id}`);
  },
  
  // Create advertisement with file upload
  create: (input: AdvertisementInput) => {
    const formData = new FormData();
    
    formData.append('title', input.title);
    formData.append('type', input.type);
    formData.append('position', input.position);
    
    if (input.media) {
      formData.append('media', input.media);
    }
    
    if (input.html_code) {
      formData.append('html_code', input.html_code);
    }
    
    if (input.target_url) {
      formData.append('target_url', input.target_url);
    }
    
    formData.append('open_in_new_tab', input.open_in_new_tab ? '1' : '0');
    formData.append('is_active', input.is_active !== false ? '1' : '0');
    
    if (input.order !== undefined) {
      formData.append('order', input.order.toString());
    }
    
    if (input.display_settings) {
      formData.append('display_settings', JSON.stringify(input.display_settings));
    }
    
    return HttpClient.post<Advertisement>(API_ENDPOINTS.ADVERTISEMENTS, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
  },
  
  // Update advertisement with file upload
  update: (id: number, input: AdvertisementInput) => {
    const formData = new FormData();
    
    formData.append('title', input.title);
    formData.append('type', input.type);
    formData.append('position', input.position);
    
    if (input.media) {
      formData.append('media', input.media);
    }
    
    if (input.html_code) {
      formData.append('html_code', input.html_code);
    }
    
    if (input.target_url) {
      formData.append('target_url', input.target_url);
    }
    
    formData.append('open_in_new_tab', input.open_in_new_tab ? '1' : '0');
    formData.append('is_active', input.is_active !== false ? '1' : '0');
    
    if (input.order !== undefined) {
      formData.append('order', input.order.toString());
    }
    
    if (input.display_settings) {
      formData.append('display_settings', JSON.stringify(input.display_settings));
    }
    
    formData.append('_method', 'PUT');
    
    return HttpClient.post<Advertisement>(`${API_ENDPOINTS.ADVERTISEMENTS}/${id}`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
  },
  
  // Delete advertisement
  delete: (id: number) => {
    return HttpClient.delete<{ message: string }>(`${API_ENDPOINTS.ADVERTISEMENTS}/${id}`);
  },
  
  // Toggle advertisement status
  toggleStatus: (id: number) => {
    return HttpClient.post<Advertisement>(`${API_ENDPOINTS.ADVERTISEMENTS}/${id}/toggle-status`, {});
  },
  
  // Update advertisements order
  updateOrder: (items: Array<{ id: number; order: number }>) => {
    return HttpClient.post(`${API_ENDPOINTS.ADVERTISEMENTS}/update-order`, { items });
  },
  
  // Get position dimensions
  getPositionDimensions: () => {
    return HttpClient.get(`${API_ENDPOINTS.ADVERTISEMENTS}/position-dimensions`);
  },
  
  // Get active advertisements (for frontend)
  getAllActive: () => {
    return HttpClient.get(`${API_ENDPOINTS.ADVERTISEMENTS}/active`);
  },
  
  // Get advertisements by position (for frontend)
  getByPosition: (position: string) => {
    return HttpClient.get(`${API_ENDPOINTS.ADVERTISEMENTS}/position/${position}`);
  },
};
