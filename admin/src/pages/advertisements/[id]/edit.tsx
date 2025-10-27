import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { GetServerSideProps } from 'next';
import { useRouter } from 'next/router';
import Layout from '@/components/layouts/admin';
import AdvertisementForm from '@/components/advertisements/advertisement-form';
import {
  useAdvertisementQuery,
  useUpdateAdvertisementMutation,
  AdvertisementInput,
} from '@/data/advertisements';
import { adminOnly } from '@/utils/auth-utils';
import { Routes } from '@/config/routes';
import ErrorMessage from '@/components/ui/error-message';
import Loader from '@/components/ui/loader/loader';

export default function EditAdvertisementPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const { id } = router.query;

  const { data, isLoading, error } = useAdvertisementQuery(Number(id));
  const { mutate: updateAdvertisement, isLoading: updating } = useUpdateAdvertisementMutation();

  const onSubmit = async (values: AdvertisementInput) => {
    updateAdvertisement(
      { id: Number(id), input: values },
      {
        onSuccess: () => {
          router.push(Routes.advertisement.list);
        },
      }
    );
  };

  if (isLoading) return <Loader text={t('common:text-loading')} />;
  if (error) return <ErrorMessage message={error.message} />;

  return (
    <>
      <div className="flex border-b border-dashed border-border-base pb-5 md:pb-7">
        <h1 className="text-lg font-semibold text-heading">
          {t('form:form-title-edit-advertisement')}
        </h1>
      </div>
      <AdvertisementForm initialValues={data} onSubmit={onSubmit} loading={updating} />
    </>
  );
}

EditAdvertisementPage.authenticate = {
  permissions: adminOnly,
};

EditAdvertisementPage.Layout = Layout;

export const getServerSideProps: GetServerSideProps = async ({ locale }) => ({
  props: {
    ...(await serverSideTranslations(locale!, ['common', 'form'])),
  },
});