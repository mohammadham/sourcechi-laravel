import { useState } from 'react';
import { useRouter } from 'next/router';
import { useTranslation } from 'next-i18next';
import {
  TelegramSession,
  useDeleteTelegramSessionMutation,
  useSetDefaultMutation,
  useToggleActiveMutation,
  useLogoutMutation,
  useTestHealthMutation,
} from '@/data/telegram-session';
import { Routes } from '@/config/routes';
import ActionButtons from '@/components/common/action-buttons';
import SessionHealthIndicator from './session-health-indicator';
import { toast } from 'react-toastify';
import { useModalAction } from '@/components/ui/modal/modal.context';

interface SessionListProps {
  sessions: TelegramSession[];
  onRefresh?: () => void;
}

export default function SessionList({ sessions, onRefresh }: SessionListProps) {
  const { t } = useTranslation('common');
  const router = useRouter();
  const { openModal } = useModalAction();

  const deleteMutation = useDeleteTelegramSessionMutation();
  const setDefaultMutation = useSetDefaultMutation();
  const toggleActiveMutation = useToggleActiveMutation();
  const logoutMutation = useLogoutMutation();
  const testHealthMutation = useTestHealthMutation();

  const [loadingStates, setLoadingStates] = useState<{
    [key: number]: string | null;
  }>({});

  const setLoading = (id: number, action: string | null) => {
    setLoadingStates((prev) => ({ ...prev, [id]: action }));
  };

  const handleDelete = async (id: number) => {
    try {
      setLoading(id, 'delete');
      await deleteMutation.mutateAsync(id);
      onRefresh?.();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-delete-error'));
    } finally {
      setLoading(id, null);
    }
  };

  const handleSetDefault = async (id: number) => {
    try {
      setLoading(id, 'default');
      await setDefaultMutation.mutateAsync(id);
      onRefresh?.();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-set-default-error'));
    } finally {
      setLoading(id, null);
    }
  };

  const handleToggleActive = async (id: number) => {
    try {
      setLoading(id, 'toggle');
      await toggleActiveMutation.mutateAsync(id);
      onRefresh?.();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-toggle-error'));
    } finally {
      setLoading(id, null);
    }
  };

  const handleLogout = async (id: number) => {
    try {
      setLoading(id, 'logout');
      await logoutMutation.mutateAsync(id);
      onRefresh?.();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-logout-error'));
    } finally {
      setLoading(id, null);
    }
  };

  const handleTestHealth = async (id: number) => {
    try {
      setLoading(id, 'test');
      await testHealthMutation.mutateAsync(id);
      onRefresh?.();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-test-error'));
    } finally {
      setLoading(id, null);
    }
  };

  const getStatusBadge = (status: string) => {
    const badges = {
      authenticated: (
        <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
          ✅ {t('telegram-authenticated')}
        </span>
      ),
      not_authenticated: (
        <span className="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-1 text-xs font-medium text-orange-800">
          ⚠️ {t('telegram-not-authenticated')}
        </span>
      ),
      error: (
        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">
          ❌ {t('telegram-error')}
        </span>
      ),
      disabled: (
        <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
          🚫 {t('telegram-disabled')}
        </span>
      ),
    };
    return badges[status] || badges.not_authenticated;
  };

  if (!sessions?.length) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-8 text-center">
        <p className="text-gray-500">{t('telegram-no-sessions')}</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
      <div className="overflow-x-auto">
        <table className="w-full table-auto">
          <thead className="border-b border-gray-200 bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-start text-sm font-semibold text-gray-700">
                {t('telegram-name')}
              </th>
              <th className="px-4 py-3 text-start text-sm font-semibold text-gray-700">
                {t('telegram-phone')}
              </th>
              <th className="px-4 py-3 text-start text-sm font-semibold text-gray-700">
                {t('telegram-status')}
              </th>
              <th className="px-4 py-3 text-start text-sm font-semibold text-gray-700">
                {t('telegram-health')}
              </th>
              <th className="px-4 py-3 text-center text-sm font-semibold text-gray-700">
                {t('telegram-active-downloads')}
              </th>
              <th className="px-4 py-3 text-center text-sm font-semibold text-gray-700">
                {t('telegram-total-downloads')}
              </th>
              <th className="px-4 py-3 text-center text-sm font-semibold text-gray-700">
                {t('telegram-priority')}
              </th>
              <th className="px-4 py-3 text-center text-sm font-semibold text-gray-700">
                {t('table:table-item-actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {sessions.map((session) => {
              const isLoading = loadingStates[session.id];
              
              return (
                <tr key={session.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-gray-900">
                        {session.name}
                      </span>
                      {session.is_default && (
                        <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                          ⭐ {t('telegram-default')}
                        </span>
                      )}
                      {!session.is_active && (
                        <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">
                          {t('telegram-inactive')}
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600">
                    {session.phone}
                  </td>
                  <td className="px-4 py-3">{getStatusBadge(session.status)}</td>
                  <td className="px-4 py-3">
                    <SessionHealthIndicator
                      score={session.health_score}
                      showLabel={false}
                    />
                  </td>
                  <td className="px-4 py-3 text-center text-sm text-gray-900">
                    {session.active_downloads}
                  </td>
                  <td className="px-4 py-3 text-center text-sm text-gray-900">
                    {session.total_downloads.toLocaleString()}
                  </td>
                  <td className="px-4 py-3 text-center">
                    <span className="inline-flex items-center justify-center rounded-full bg-accent/10 px-2 py-1 text-xs font-medium text-accent">
                      {session.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-center gap-2">
                      {/* Test Health */}
                      <button
                        onClick={() => handleTestHealth(session.id)}
                        disabled={!!isLoading}
                        className="rounded p-1.5 text-gray-600 hover:bg-gray-100 hover:text-accent disabled:opacity-50"
                        title={t('telegram-test-health')}
                      >
                        {isLoading === 'test' ? (
                          <span className="animate-spin">⏳</span>
                        ) : (
                          <span>🏥</span>
                        )}
                      </button>

                      {/* Set Default */}
                      {!session.is_default && session.status === 'authenticated' && (
                        <button
                          onClick={() => handleSetDefault(session.id)}
                          disabled={!!isLoading}
                          className="rounded p-1.5 text-gray-600 hover:bg-gray-100 hover:text-yellow-600 disabled:opacity-50"
                          title={t('telegram-set-as-default')}
                        >
                          {isLoading === 'default' ? (
                            <span className="animate-spin">⏳</span>
                          ) : (
                            <span>⭐</span>
                          )}
                        </button>
                      )}

                      {/* Toggle Active */}
                      {!session.is_default && (
                        <button
                          onClick={() => handleToggleActive(session.id)}
                          disabled={!!isLoading}
                          className="rounded p-1.5 text-gray-600 hover:bg-gray-100 hover:text-green-600 disabled:opacity-50"
                          title={
                            session.is_active
                              ? t('telegram-deactivate')
                              : t('telegram-activate')
                          }
                        >
                          {isLoading === 'toggle' ? (
                            <span className="animate-spin">⏳</span>
                          ) : session.is_active ? (
                            <span>🔴</span>
                          ) : (
                            <span>🟢</span>
                          )}
                        </button>
                      )}

                      {/* Logout */}
                      {session.status === 'authenticated' && (
                        <button
                          onClick={() => handleLogout(session.id)}
                          disabled={!!isLoading}
                          className="rounded p-1.5 text-gray-600 hover:bg-gray-100 hover:text-orange-600 disabled:opacity-50"
                          title={t('telegram-logout')}
                        >
                          {isLoading === 'logout' ? (
                            <span className="animate-spin">⏳</span>
                          ) : (
                            <span>🚪</span>
                          )}
                        </button>
                      )}

                      {/* Edit */}
                      <ActionButtons
                        id={session.id.toString()}
                        editUrl={Routes.telegramSessions.edit(session.id.toString())}
                        deleteModalView="DELETE_TELEGRAM_SESSION"
                      />
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
