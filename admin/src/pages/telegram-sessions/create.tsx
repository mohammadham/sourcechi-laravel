import { useRouter } from 'next/router';
import AdminLayout from '@/components/layouts/admin';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { adminOnly } from '@/utils/auth-utils';
import SessionForm from '@/components/telegram-sessions/session-form';
import {
  TelegramSessionInput,
  useCreateTelegramSessionMutation,
} from '@/data/telegram-session';
import { Routes } from '@/config/routes';
import { toast } from 'react-toastify';

export default function CreateTelegramSessionPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const createMutation = useCreateTelegramSessionMutation();

  const handleSubmit = async (values: TelegramSessionInput) => {
    try {
      await createMutation.mutateAsync(values);
      // Navigation handled by mutation
    } catch (error: any) {
      toast.error(error?.message || t('common:telegram-create-error'));
    }
  };

  return (
    <>
      <div className="flex border-b border-dashed border-border-base pb-5 md:pb-7">
        <h1 className="text-lg font-semibold text-heading">
          {t('common:telegram-create-session')}
        </h1>
      </div>
      <SessionForm
        onSubmit={handleSubmit}
        loading={createMutation.isLoading}
      />
    </>
  );
}

CreateTelegramSessionPage.authenticate = {
  permissions: adminOnly,
};

CreateTelegramSessionPage.Layout = AdminLayout;

export const getStaticProps = async ({ locale }: any) => ({
  props: {
    ...(await serverSideTranslations(locale, ['form', 'common'])),
  },
});
