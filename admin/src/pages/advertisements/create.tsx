import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { GetStaticProps } from 'next';
import { useRouter } from 'next/router';
import Layout from '@/components/layouts/admin';
import AdvertisementForm from '@/components/advertisements/advertisement-form';
import { useCreateAdvertisementMutation } from '@/data/advertisements';
import { AdvertisementInput } from '@/data/advertisements';
import { adminOnly } from '@/utils/auth-utils';
import { Routes } from '@/config/routes';

export default function CreateAdvertisementPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const { mutate: createAdvertisement, isLoading } = useCreateAdvertisementMutation();

  const onSubmit = async (values: AdvertisementInput) => {
    createAdvertisement(values, {
      onSuccess: () => {
        router.push(Routes.advertisement.list);
      },
    });
  };

  return (
    <>
      <div className="flex border-b border-dashed border-border-base pb-5 md:pb-7">
        <h1 className="text-lg font-semibold text-heading">
          {t('form:form-title-create-advertisement')}
        </h1>
      </div>
      <AdvertisementForm onSubmit={onSubmit} loading={isLoading} />
    </>
  );
}

CreateAdvertisementPage.authenticate = {
  permissions: adminOnly,
};

CreateAdvertisementPage.Layout = Layout;

export const getStaticProps: GetStaticProps = async ({ locale }) => ({
  props: {
    ...(await serverSideTranslations(locale!, ['common', 'form'])),
  },
});