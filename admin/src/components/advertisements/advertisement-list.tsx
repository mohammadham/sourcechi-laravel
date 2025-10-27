import { Table } from '@/components/ui/table';
import ActionButtons from '@/components/common/action-buttons';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { Advertisement } from '@/data/advertisements';
import Badge from '@/components/ui/badge/badge';
import { Routes } from '@/config/routes';
import { useToggleAdvertisementStatusMutation } from '@/data/advertisements';
import { SwitchInput } from '@/components/ui/switch-input';
import { useMemo, useState } from 'react';

export type IProps = {
  advertisements: Advertisement[];
  onPagination: (current: number) => void;
  paginatorInfo: any;
};

const AdvertisementList = ({ advertisements, paginatorInfo, onPagination }: IProps) => {
  const { t } = useTranslation();
  const router = useRouter();
  const { mutate: toggleStatus } = useToggleAdvertisementStatusMutation();

  const positionLabels: Record<string, string> = {
    header: t('form:advertisement-position-header'),
    sidebar: t('form:advertisement-position-sidebar'),
    footer: t('form:advertisement-position-footer'),
    between_products: t('form:advertisement-position-between-products'),
    product_detail: t('form:advertisement-position-product-detail'),
    popup: t('form:advertisement-position-popup'),
  };

  const typeLabels: Record<string, string> = {
    image: t('form:advertisement-type-image'),
    video: t('form:advertisement-type-video'),
    html: t('form:advertisement-type-html'),
  };

  const columns = useMemo(
    () => [
      {
        title: t('table:table-item-id'),
        dataIndex: 'id',
        key: 'id',
        align: 'center',
        width: 80,
      },
      {
        title: t('form:input-label-title'),
        dataIndex: 'title',
        key: 'title',
        align: 'left',
        width: 200,
      },
      {
        title: t('form:input-label-type'),
        dataIndex: 'type',
        key: 'type',
        align: 'center',
        width: 120,
        render: (type: string) => (
          <Badge text={typeLabels[type] || type} />
        ),
      },
      {
        title: t('form:input-label-position'),
        dataIndex: 'position',
        key: 'position',
        align: 'left',
        width: 180,
        render: (position: string) => positionLabels[position] || position,
      },
      {
        title: t('form:input-label-order'),
        dataIndex: 'order',
        key: 'order',
        align: 'center',
        width: 100,
      },
      {
        title: t('table:table-item-status'),
        dataIndex: 'is_active',
        key: 'is_active',
        align: 'center',
        width: 120,
        render: (is_active: boolean, record: Advertisement) => (
          <SwitchInput
            name={`is_active_${record.id}`}
            checked={is_active}
            onChange={() => toggleStatus(record.id)}
          />
        ),
      },
      {
        title: t('table:table-item-actions'),
        dataIndex: 'id',
        key: 'actions',
        align: 'center',
        width: 120,
        render: (id: number) => (
          <ActionButtons
            id={id}
            editUrl={`${Routes.advertisement.list}/${id}/edit`}
            deleteModalView="DELETE_ADVERTISEMENT"
          />
        ),
      },
    ],
    [t, toggleStatus, positionLabels, typeLabels]
  );

  return (
    <>
      <div className="mb-8 overflow-hidden rounded shadow">
        <Table
          /* @ts-ignore */
          columns={columns}
          emptyText={t('table:empty-table-data')}
          data={advertisements}
          rowKey="id"
          scroll={{ x: 900 }}
        />
      </div>

      {!!paginatorInfo?.total && (
        <div className="flex items-center justify-end">
          <Pagination
            total={paginatorInfo.total}
            current={paginatorInfo.currentPage}
            pageSize={paginatorInfo.perPage}
            onChange={onPagination}
          />
        </div>
      )}
    </>
  );
};

export default AdvertisementList;

function Pagination({
  total,
  current,
  pageSize,
  onChange,
}: {
  total: number;
  current: number;
  pageSize: number;
  onChange: (page: number) => void;
}) {
  return (
    <div className="flex items-center gap-2">
      <button
        onClick={() => onChange(current - 1)}
        disabled={current === 1}
        className="px-3 py-1 text-sm border rounded disabled:opacity-50"
      >
        قبلی
      </button>
      <span className="text-sm">
        صفحه {current} از {Math.ceil(total / pageSize)}
      </span>
      <button
        onClick={() => onChange(current + 1)}
        disabled={current >= Math.ceil(total / pageSize)}
        className="px-3 py-1 text-sm border rounded disabled:opacity-50"
      >
        بعدی
      </button>
    </div>
  );
}