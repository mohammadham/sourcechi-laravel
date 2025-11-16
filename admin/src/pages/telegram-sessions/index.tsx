import { useState } from 'react';
import AdminLayout from '@/components/layouts/admin';
import Card from '@/components/common/card';
import PageHeading from '@/components/common/page-heading';
import ErrorMessage from '@/components/ui/error-message';
import Loader from '@/components/ui/loader/loader';
import { useTelegramSessionsQuery, useCheckAllHealthMutation } from '@/data/telegram-session';
import { adminOnly } from '@/utils/auth-utils';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import { Routes } from '@/config/routes';
import LinkButton from '@/components/ui/link-button';
import SessionList from '@/components/telegram-sessions/session-list';
import SessionStats from '@/components/telegram-sessions/session-stats';
import Button from '@/components/ui/button';
import { toast } from 'react-toastify';

export default function TelegramSessionsPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const [refreshKey, setRefreshKey] = useState(0);

  const { sessions, loading, error } = useTelegramSessionsQuery(
    {},
    { refetchInterval: false, refetchOnWindowFocus: false, cacheTime: 0, staleTime: 0, key: refreshKey }
  );

  const checkAllHealthMutation = useCheckAllHealthMutation();

  const handleRefresh = () => {
    setRefreshKey(prev => prev + 1);
  };

  const handleCheckAllHealth = async () => {
    try {
      await checkAllHealthMutation.mutateAsync();
      handleRefresh();
    } catch (error: any) {
      toast.error(error?.message || t('common:telegram-check-health-error'));
    }
  };

  if (loading) return <Loader text={t('common:text-loading')} />;
  if (error) return <ErrorMessage message={error.message} />;

  return (
    <>
      {/* Stats Dashboard */}
      <SessionStats onRefresh={handleRefresh} />

      {/* Header with Actions */}
      <Card className="mb-8 flex flex-col items-center justify-between md:flex-row">
        <div className="mb-4 md:mb-0 md:w-1/4">
          <PageHeading title={t('common:telegram-sessions')} />
        </div>

        <div className="flex w-full flex-col items-center space-y-4 ms-auto md:w-3/4 md:flex-row md:space-y-0 md:space-s-4 xl:w-2/4">
          <Button
            onClick={handleCheckAllHealth}
            loading={checkAllHealthMutation.isLoading}
            className="w-full md:w-auto"
            variant="outline"
          >
            <svg
              className="me-2 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            {t('common:telegram-check-all-health')}
          </Button>

          <LinkButton
            href={Routes.telegramSessions.create}
            className="w-full md:w-auto"
          >
            <span className="hidden md:block">
              + {t('common:telegram-add-session')}
            </span>
            <span className="md:hidden">+ {t('form:button-label-add')}</span>
          </LinkButton>
        </div>
      </Card>

      {/* Sessions List */}
      <SessionList sessions={sessions} onRefresh={handleRefresh} />
    </>
  );
}

TelegramSessionsPage.authenticate = {
  permissions: adminOnly,
};

TelegramSessionsPage.Layout = AdminLayout;

export const getStaticProps = async ({ locale }: any) => ({
  props: {
    ...(await serverSideTranslations(locale, ['table', 'common', 'form'])),
  },
});
