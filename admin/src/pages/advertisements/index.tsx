import { useState } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { GetStaticProps } from 'next';
import { useRouter } from 'next/router';
import Layout from '@/components/layouts/admin';
import Card from '@/components/common/card';
import Search from '@/components/common/search';
import LinkButton from '@/components/ui/link-button';
import { Routes } from '@/config/routes';
import { useAdvertisementsQuery, useDeleteAdvertisementMutation } from '@/data/advertisements';
import AdvertisementList from '@/components/advertisements/advertisement-list';
import ErrorMessage from '@/components/ui/error-message';
import Loader from '@/components/ui/loader/loader';
import { adminOnly } from '@/utils/auth-utils';
import { useModalAction } from '@/components/ui/modal/modal.context';

export default function AdvertisementsPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const [searchTerm, setSearchTerm] = useState('');
  const [page, setPage] = useState(1);
  const { openModal } = useModalAction();

  const { data, isLoading, error } = useAdvertisementsQuery({
    limit: 15,
    page,
    search: searchTerm,
  });

  const { mutate: deleteAdvertisement } = useDeleteAdvertisementMutation();

  function handleSearch({ searchText }: { searchText: string }) {
    setSearchTerm(searchText);
    setPage(1);
  }

  function handlePagination(current: number) {
    setPage(current);
  }

  if (error) return <ErrorMessage message={error.message} />;

  return (
    <>
      <Card className="mb-8 flex flex-col items-center xl:flex-row">
        <div className="mb-4 md:w-1/4 xl:mb-0">
          <h1 className="text-xl font-semibold text-heading">
            {t('form:advertisement-list-title')}
          </h1>
        </div>

        <div className="flex w-full flex-col items-center space-y-4 ms-auto md:flex-row md:space-y-0 xl:w-1/2">
          <Search onSearch={handleSearch} />

          <LinkButton
            href={Routes.advertisement.create}
            className="h-12 w-full md:w-auto md:ms-6"
          >
            <span className="block md:hidden xl:block">
              + {t('form:button-label-add-advertisement')}
            </span>
            <span className="hidden md:block xl:hidden">+ {t('form:button-label-add')}</span>
          </LinkButton>
        </div>
      </Card>

      {isLoading ? (
        <Loader text={t('common:text-loading')} />
      ) : (
        <AdvertisementList
          advertisements={data?.data || []}
          paginatorInfo={{
            total: data?.total || 0,
            currentPage: data?.current_page || 1,
            perPage: data?.per_page || 15,
          }}
          onPagination={handlePagination}
        />
      )}
    </>
  );
}

AdvertisementsPage.authenticate = {
  permissions: adminOnly,
};

AdvertisementsPage.Layout = Layout;

export const getStaticProps: GetStaticProps = async ({ locale }) => ({
  props: {
    ...(await serverSideTranslations(locale!, ['common', 'form', 'table'])),
  },
});