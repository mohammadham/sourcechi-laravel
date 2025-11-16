import Router, { useRouter } from 'next/router';
import { useMutation, useQuery, useQueryClient } from 'react-query';
import { toast } from 'react-toastify';
import { useTranslation } from 'next-i18next';
import { Routes } from '@/config/routes';
import { API_ENDPOINTS } from './client/api-endpoints';
import { HttpClient } from './client/http-client';

// Types
export interface TelegramSession {
  id: number;
  name: string;
  phone: string;
  api_id: number;
  api_hash: string;
  channel_id: string;
  is_default: boolean;
  is_active: boolean;
  priority: number;
  status: 'authenticated' | 'not_authenticated' | 'error' | 'disabled';
  health_score: number;
  last_health_check: string | null;
  health_error: string | null;
  active_downloads: number;
  total_downloads: number;
  total_uploads: number;
  last_used_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface TelegramSessionStats {
  total_sessions: number;
  active_sessions: number;
  healthy_sessions: number;
  total_active_downloads: number;
  total_downloads_today: number;
}

export interface TelegramSessionInput {
  name: string;
  phone: string;
  api_id: number;
  api_hash: string;
  channel_id: string;
  is_default?: boolean;
  is_active?: boolean;
  priority?: number;
}

export interface TelegramSessionQueryOptions {
  page?: number;
  limit?: number;
  status?: string;
  is_active?: boolean;
  orderBy?: string;
  sortedBy?: 'asc' | 'desc';
}

export interface LoginStartResponse {
  success: boolean;
  message: string;
  phone_code_hash?: string;
}

export interface LoginVerifyInput {
  code: string;
  phone_code_hash: string;
}

export interface Login2FAInput {
  password: string;
}

// API Client
class TelegramSessionClient {
  async all(params?: TelegramSessionQueryOptions) {
    return HttpClient.get<{ data: TelegramSession[] }>(API_ENDPOINTS.TELEGRAM_SESSIONS, params);
  }

  async get(id: number) {
    return HttpClient.get<TelegramSession>(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}`);
  }

  async create(input: TelegramSessionInput) {
    return HttpClient.post<TelegramSession>(API_ENDPOINTS.TELEGRAM_SESSIONS, input);
  }

  async update(id: number, input: Partial<TelegramSessionInput>) {
    return HttpClient.put<TelegramSession>(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}`, input);
  }

  async delete(id: number) {
    return HttpClient.delete(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}`);
  }

  async startLogin(id: number) {
    return HttpClient.post<LoginStartResponse>(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/login/start`, {});
  }

  async verifyCode(id: number, input: LoginVerifyInput) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/login/verify`, input);
  }

  async verify2FA(id: number, input: Login2FAInput) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/login/2fa`, input);
  }

  async testHealth(id: number) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/test`, {});
  }

  async setDefault(id: number) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/set-default`, {});
  }

  async toggleActive(id: number) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/toggle-active`, {});
  }

  async logout(id: number) {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/${id}/logout`, {});
  }

  async getStats() {
    return HttpClient.get<TelegramSessionStats>(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/stats`);
  }

  async checkAllHealth() {
    return HttpClient.post(`${API_ENDPOINTS.TELEGRAM_SESSIONS}/check-health`, {});
  }
}

export const telegramSessionClient = new TelegramSessionClient();

// React Query Hooks
export const useCreateTelegramSessionMutation = () => {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation(telegramSessionClient.create, {
    onSuccess: () => {
      Router.push(Routes.telegramSessions.list);
      toast.success(t('common:telegram-session-created-success'));
    },
    onSettled: () => {
      queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
    },
  });
};

export const useUpdateTelegramSessionMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    ({ id, input }: { id: number; input: Partial<TelegramSessionInput> }) =>
      telegramSessionClient.update(id, input),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-session-updated-success'));
      },
      onSettled: () => {
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useDeleteTelegramSessionMutation = () => {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation(telegramSessionClient.delete, {
    onSuccess: () => {
      toast.success(t('common:telegram-session-deleted-success'));
    },
    onSettled: () => {
      queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
    },
  });
};

export const useTelegramSessionQuery = (id: number) => {
  return useQuery<TelegramSession, Error>(
    [API_ENDPOINTS.TELEGRAM_SESSIONS, id],
    () => telegramSessionClient.get(id)
  );
};

export const useTelegramSessionsQuery = (
  params?: TelegramSessionQueryOptions,
  options: any = {}
) => {
  const { data, error, isLoading } = useQuery<{ data: TelegramSession[] }, Error>(
    [API_ENDPOINTS.TELEGRAM_SESSIONS, params],
    () => telegramSessionClient.all(params),
    {
      keepPreviousData: true,
      ...options,
    }
  );

  return {
    sessions: data?.data ?? [],
    error,
    loading: isLoading,
  };
};

export const useTelegramSessionStatsQuery = (options: any = {}) => {
  const { data, error, isLoading, refetch } = useQuery<TelegramSessionStats, Error>(
    [API_ENDPOINTS.TELEGRAM_SESSIONS, 'stats'],
    () => telegramSessionClient.getStats(),
    {
      ...options,
    }
  );

  return {
    stats: data,
    error,
    loading: isLoading,
    refetch,
  };
};

export const useStartLoginMutation = () => {
  const { t } = useTranslation();
  
  return useMutation(
    (id: number) => telegramSessionClient.startLogin(id),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-login-code-sent'));
      },
    }
  );
};

export const useVerifyCodeMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    ({ id, input }: { id: number; input: LoginVerifyInput }) =>
      telegramSessionClient.verifyCode(id, input),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-login-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useVerify2FAMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    ({ id, input }: { id: number; input: Login2FAInput }) =>
      telegramSessionClient.verify2FA(id, input),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-login-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useTestHealthMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    (id: number) => telegramSessionClient.testHealth(id),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-health-test-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useSetDefaultMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    (id: number) => telegramSessionClient.setDefault(id),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-set-default-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useToggleActiveMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    (id: number) => telegramSessionClient.toggleActive(id),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-toggle-active-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useLogoutMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    (id: number) => telegramSessionClient.logout(id),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-logout-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};

export const useCheckAllHealthMutation = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  
  return useMutation(
    () => telegramSessionClient.checkAllHealth(),
    {
      onSuccess: () => {
        toast.success(t('common:telegram-check-all-health-success'));
        queryClient.invalidateQueries(API_ENDPOINTS.TELEGRAM_SESSIONS);
      },
    }
  );
};
