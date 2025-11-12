import { useState, useEffect } from 'react';
import Card from '@/components/common/card';
import Description from '@/components/ui/description';
import Button from '@/components/ui/button';
import Alert from '@/components/ui/alert';
import { useTranslation } from 'next-i18next';
import axios from 'axios';
import Cookies from 'js-cookie';
import { AUTH_CRED } from '@/utils/constants';

// Create axios instance with baseURL
const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_REST_API_ENDPOINT,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add auth token to requests
apiClient.interceptors.request.use((config) => {
  const authCred = Cookies.get(AUTH_CRED);
  if (authCred) {
    try {
      // Parse the JSON string to get the token
      const credentials = JSON.parse(authCred);
      const token = credentials?.token;
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      console.error('[Cache Manager] Failed to parse auth credentials:', error);
    }
  }
  return config;
});

interface CacheStats {
  total_files: number;
  total_size: number;
  total_size_formatted: string;
  oldest_file?: string;
  newest_file?: string;
}

export default function CacheManager() {
  const { t } = useTranslation();
  const [stats, setStats] = useState<CacheStats | null>(null);
  const [loading, setLoading] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [alert, setAlert] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  const fetchStats = async () => {
    try {
      setLoading(true);
      console.log('[Cache Manager] Fetching cache stats...');
      
      const response = await apiClient.get('/api/storage/cache/stats');
      
      console.log('[Cache Manager] Stats received:', response.data);
      setStats(response.data);
    } catch (error: any) {
      console.error('[Cache Manager] Failed to fetch stats:', error);
      setAlert({
        message: error.response?.data?.message || t('form:error-fetching-cache-stats'),
        type: 'error',
      });
    } finally {
      setLoading(false);
    }
  };

  const clearCache = async (olderThan?: number, all?: boolean) => {
    try {
      setClearing(true);
      setAlert(null);
      
      console.log('[Cache Manager] Clearing cache...', { olderThan, all });
      
      const response = await apiClient.post('/api/storage/cache/clear-telegram', {
        older_than: olderThan,
        all: all,
      });
      
      console.log('[Cache Manager] Cache cleared:', response.data);
      
      setAlert({
        message: response.data.message || t('form:cache-cleared-successfully'),
        type: 'success',
      });
      
      // Refresh stats after clearing
      await fetchStats();
    } catch (error: any) {
      console.error('[Cache Manager] Failed to clear cache:', error);
      setAlert({
        message: error.response?.data?.message || t('form:error-clearing-cache'),
        type: 'error',
      });
    } finally {
      setClearing(false);
    }
  };

  // Fetch stats on mount
  useEffect(() => {
    fetchStats();
  }, []);

  // Auto-refresh every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      if (!clearing) {
        fetchStats();
      }
    }, 30000);

    return () => clearInterval(interval);
  }, [clearing]);

  return (
    <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base">
      <Description
        title={t('form:storage-cache-title')}
        details={t('form:storage-cache-help-text')}
        className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
      />

      <Card className="w-full sm:w-8/12 md:w-2/3">
        {alert && (
          <div className="mb-5">
            <Alert
              message={alert.message}
              variant={alert.type === 'success' ? 'success' : 'error'}
              closeable={true}
              onClose={() => setAlert(null)}
            />
          </div>
        )}

        {/* Cache Statistics */}
        <div className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <h3 className="text-lg font-semibold mb-3">
            {t('form:cache-statistics')}
          </h3>
          
          {loading ? (
            <div className="text-center py-4">
              <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {t('form:loading-cache-stats')}
              </p>
            </div>
          ) : stats ? (
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  {t('form:total-cached-files')}
                </p>
                <p className="text-2xl font-bold text-accent">
                  {stats.total_files}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  {t('form:total-cache-size')}
                </p>
                <p className="text-2xl font-bold text-accent">
                  {stats.total_size_formatted}
                </p>
              </div>
              {stats.oldest_file && (
                <div className="col-span-2">
                  <p className="text-sm text-gray-600 dark:text-gray-400">
                    {t('form:oldest-cached-file')}
                  </p>
                  <p className="text-sm font-medium">
                    {new Date(stats.oldest_file).toLocaleString()}
                  </p>
                </div>
              )}
            </div>
          ) : (
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {t('form:no-cache-data')}
            </p>
          )}
        </div>

        {/* Cache Management Actions */}
        <div className="space-y-3">
          <h3 className="text-lg font-semibold mb-3">
            {t('form:cache-management-actions')}
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <Button
              onClick={() => clearCache(7, false)}
              disabled={clearing || loading}
              loading={clearing}
              variant="outline"
              className="w-full"
            >
              {t('form:clear-7-days')}
            </Button>

            <Button
              onClick={() => clearCache(30, false)}
              disabled={clearing || loading}
              loading={clearing}
              variant="outline"
              className="w-full"
            >
              {t('form:clear-30-days')}
            </Button>

            <Button
              onClick={() => {
                if (confirm(t('form:confirm-clear-all-cache'))) {
                  clearCache(undefined, true);
                }
              }}
              disabled={clearing || loading}
              loading={clearing}
              variant="outline"
              className="w-full border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
            >
              {t('form:clear-all-cache')}
            </Button>
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
            {t('form:cache-clear-note')}
          </p>
        </div>

        {/* Manual Refresh Button */}
        <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <Button
            onClick={fetchStats}
            disabled={loading || clearing}
            loading={loading}
            variant="outline"
            size="small"
            className="w-full md:w-auto"
          >
            {t('form:refresh-cache-stats')}
          </Button>
        </div>
      </Card>
    </div>
  );
}
