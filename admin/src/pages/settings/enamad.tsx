import AdminLayout from '@/components/layouts/admin';
import EnamadSettingsForm from '@/components/settings/enamad';
import ErrorMessage from '@/components/ui/error-message';
import Loader from '@/components/ui/loader/loader';
import { useSettingsQuery } from '@/data/settings';
import { adminOnly } from '@/utils/auth-utils';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import SettingsPageHeader from '@/components/settings/settings-page-header';

export default function EnamadSettings() {
  const { t } = useTranslation();
  const { locale } = useRouter();

  const { settings, loading, error } = useSettingsQuery({
    language: locale!,
  });

  if (loading) return <Loader text={t('common:text-loading')} />;
  if (error) return <ErrorMessage message={error.message} />;

  return (
    <>
      <SettingsPageHeader pageTitle="form:enamad-settings-title" />
      <EnamadSettingsForm settings={settings} />
    </>
  );
}

EnamadSettings.authenticate = {
  permissions: adminOnly,
};

EnamadSettings.Layout = AdminLayout;

export const getStaticProps = async ({ locale }: any) => ({
  props: {
    ...(await serverSideTranslations(locale, ['form', 'common'])),
  },
});
