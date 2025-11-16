import { useRouter } from 'next/router';
import AdminLayout from '@/components/layouts/admin';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { adminOnly } from '@/utils/auth-utils';
import SessionForm from '@/components/telegram-sessions/session-form';
import {
  TelegramSessionInput,
  useTelegramSessionQuery,
  useUpdateTelegramSessionMutation,
} from '@/data/telegram-session';
import ErrorMessage from '@/components/ui/error-message';
import Loader from '@/components/ui/loader/loader';
import { toast } from 'react-toastify';

export default function EditTelegramSessionPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const { id } = router.query;

  const { data: session, isLoading, error } = useTelegramSessionQuery(Number(id));
  const updateMutation = useUpdateTelegramSessionMutation();

  const handleSubmit = async (values: TelegramSessionInput) => {
    try {
      await updateMutation.mutateAsync({
        id: Number(id),
        input: values,
      });
      toast.success(t('common:telegram-session-updated-success'));
    } catch (error: any) {
      toast.error(error?.message || t('common:telegram-update-error'));
    }
  };

  if (isLoading) return <Loader text={t('common:text-loading')} />;
  if (error) return <ErrorMessage message={error.message} />;
  if (!session) return <ErrorMessage message={t('common:telegram-session-not-found')} />;

  return (
    <>
      <div className="flex border-b border-dashed border-border-base pb-5 md:pb-7">
        <h1 className="text-lg font-semibold text-heading">
          {t('common:telegram-edit-session')}: {session.name}
        </h1>
      </div>
      <SessionForm
        initialValues={session}
        onSubmit={handleSubmit}
        loading={updateMutation.isLoading}
      />
    </>
  );
}

EditTelegramSessionPage.authenticate = {
  permissions: adminOnly,
};

EditTelegramSessionPage.Layout = AdminLayout;

export const getServerSideProps = async ({ locale }: any) => ({
  props: {
    ...(await serverSideTranslations(locale, ['form', 'common'])),
  },
});
