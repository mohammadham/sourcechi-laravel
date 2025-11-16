import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'next-i18next';
import Card from '@/components/common/card';
import Button from '@/components/ui/button';
import Input from '@/components/ui/input';
import Description from '@/components/ui/description';
import {
  TelegramSessionInput,
  TelegramSession,
} from '@/data/telegram-session';
import SessionLoginModal from './session-login-modal';
import SessionHealthIndicator from './session-health-indicator';

interface SessionFormProps {
  initialValues?: TelegramSession | null;
  onSubmit: (values: TelegramSessionInput) => void;
  loading: boolean;
}

export default function SessionForm({
  initialValues,
  onSubmit,
  loading,
}: SessionFormProps) {
  const { t } = useTranslation('common');
  const [showLoginModal, setShowLoginModal] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
    watch,
  } = useForm<TelegramSessionInput>({
    defaultValues: initialValues
      ? {
          name: initialValues.name,
          phone: initialValues.phone,
          api_id: initialValues.api_id,
          api_hash: initialValues.api_hash,
          channel_id: initialValues.channel_id,
          priority: initialValues.priority,
          is_active: initialValues.is_active,
          is_default: initialValues.is_default,
        }
      : {
          priority: 5,
          is_active: true,
          is_default: false,
        },
  });

  const isDefault = watch('is_default');

  return (
    <>
      <form onSubmit={handleSubmit(onSubmit)}>
        <div className="my-5 flex flex-wrap sm:my-8">
          <Description
            title={t('telegram-session-information')}
            details={t('telegram-session-information-description')}
            className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pe-4 md:w-1/3 md:pe-5"
          />

          <Card className="w-full sm:w-8/12 md:w-2/3">
            {/* Session Stats (Edit mode only) */}
            {initialValues && (
              <div className="mb-6 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('telegram-status')}:</span>
                  <span className="text-sm">
                    {initialValues.status === 'authenticated' && (
                      <span className="text-green-600">✅ {t('telegram-authenticated')}</span>
                    )}
                    {initialValues.status === 'not_authenticated' && (
                      <span className="text-orange-600">⚠️ {t('telegram-not-authenticated')}</span>
                    )}
                    {initialValues.status === 'error' && (
                      <span className="text-red-600">❌ {t('telegram-error')}</span>
                    )}
                    {initialValues.status === 'disabled' && (
                      <span className="text-gray-600">🚫 {t('telegram-disabled')}</span>
                    )}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('telegram-health-score')}:</span>
                  <SessionHealthIndicator score={initialValues.health_score} />
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('telegram-total-downloads')}:</span>
                  <span className="text-sm">{initialValues.total_downloads.toLocaleString()}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('telegram-total-uploads')}:</span>
                  <span className="text-sm">{initialValues.total_uploads.toLocaleString()}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('telegram-active-downloads')}:</span>
                  <span className="text-sm">{initialValues.active_downloads}</span>
                </div>
              </div>
            )}

            {/* Name */}
            <div className="mb-5">
              <Input
                label={t('telegram-session-name')}
                {...register('name', {
                  required: t('form:error-name-required') as string,
                })}
                error={errors.name?.message}
                variant="outline"
                placeholder={t('telegram-session-name-placeholder')}
              />
            </div>

            {/* Phone */}
            <div className="mb-5">
              <Input
                label={t('telegram-phone')}
                {...register('phone', {
                  required: t('form:error-phone-required') as string,
                })}
                error={errors.phone?.message}
                variant="outline"
                placeholder="+989123456789"
                disabled={!!initialValues}
              />
              {initialValues && (
                <p className="mt-1 text-xs text-gray-500">
                  {t('telegram-phone-cannot-change')}
                </p>
              )}
            </div>

            {/* API ID */}
            <div className="mb-5">
              <Input
                label={t('telegram-api-id')}
                type="number"
                {...register('api_id', {
                  required: t('form:error-api-id-required') as string,
                  valueAsNumber: true,
                })}
                error={errors.api_id?.message}
                variant="outline"
                placeholder="12345678"
              />
            </div>

            {/* API Hash */}
            <div className="mb-5">
              <Input
                label={t('telegram-api-hash')}
                {...register('api_hash', {
                  required: t('form:error-api-hash-required') as string,
                })}
                error={errors.api_hash?.message}
                variant="outline"
                placeholder="abcdef1234567890abcdef1234567890"
              />
            </div>

            {/* Channel ID */}
            <div className="mb-5">
              <Input
                label={t('telegram-channel-id')}
                {...register('channel_id', {
                  required: t('form:error-channel-id-required') as string,
                })}
                error={errors.channel_id?.message}
                variant="outline"
                placeholder="-1001234567890"
              />
            </div>

            {/* Priority */}
            <div className="mb-5">
              <Input
                label={t('telegram-priority')}
                type="number"
                {...register('priority', {
                  required: t('form:error-priority-required') as string,
                  valueAsNumber: true,
                  min: {
                    value: 1,
                    message: t('form:error-priority-min'),
                  },
                  max: {
                    value: 10,
                    message: t('form:error-priority-max'),
                  },
                })}
                error={errors.priority?.message}
                variant="outline"
                placeholder="5"
              />
              <p className="mt-1 text-xs text-gray-500">
                {t('telegram-priority-description')}
              </p>
            </div>

            {/* Is Active */}
            <div className="mb-5">
              <label className="flex items-center">
                <input
                  type="checkbox"
                  {...register('is_active')}
                  className="h-4 w-4 rounded border-gray-300 text-accent focus:ring-accent"
                />
                <span className="ms-2 text-sm font-medium text-body">
                  {t('telegram-is-active')}
                </span>
              </label>
            </div>

            {/* Is Default */}
            <div className="mb-5">
              <label className="flex items-center">
                <input
                  type="checkbox"
                  {...register('is_default')}
                  className="h-4 w-4 rounded border-gray-300 text-accent focus:ring-accent"
                />
                <span className="ms-2 text-sm font-medium text-body">
                  {t('telegram-is-default')}
                </span>
              </label>
              {isDefault && (
                <p className="mt-1 text-xs text-orange-600">
                  ⚠️ {t('telegram-default-warning')}
                </p>
              )}
            </div>

            {/* Login Button (Edit mode only) */}
            {initialValues && initialValues.status !== 'authenticated' && (
              <div className="mb-5">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setShowLoginModal(true)}
                  className="w-full"
                >
                  {t('telegram-login-to-session')}
                </Button>
              </div>
            )}
          </Card>
        </div>

        {/* Submit Button */}
        <div className="mb-4 text-end">
          <Button loading={loading} disabled={loading}>
            {initialValues ? t('form:button-label-update') : t('form:button-label-create')}
          </Button>
        </div>
      </form>

      {/* Login Modal */}
      {initialValues && (
        <SessionLoginModal
          open={showLoginModal}
          onClose={() => setShowLoginModal(false)}
          sessionId={initialValues.id}
          onSuccess={() => {
            window.location.reload();
          }}
        />
      )}
    </>
  );
}
