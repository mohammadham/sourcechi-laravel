import Card from '@/components/common/card';
import Button from '@/components/ui/button';
import Description from '@/components/ui/description';
import Input from '@/components/ui/input';
import Label from '@/components/ui/label';
import SelectInput from '@/components/ui/select-input';
import StickyFooterPanel from '@/components/ui/sticky-footer-panel';
import SwitchInput from '@/components/ui/switch-input';
import TextArea from '@/components/ui/text-area';
import { useUpdateSettingsMutation } from '@/data/settings';
import { Settings } from '@/types';
import { yupResolver } from '@hookform/resolvers/yup';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { useForm } from 'react-hook-form';
import { enamadValidationSchema } from '@/components/settings/enamad/enamad-validation-schema';
import { SaveIcon } from '@/components/icons/save';
import { useConfirmRedirectIfDirty } from '@/utils/confirmed-redirect-if-dirty';
import Alert from '@/components/ui/alert';

type EnamadFormValues = {
  enamad: {
    enabled: boolean;
    code: string;
    link: string;
    displayLocation: string;
  };
};

type IProps = {
  settings?: Settings | null;
};

const displayLocationOptions = [
  { value: 'footer', label: 'فوتر (Footer)' },
  { value: 'sidebar', label: 'نوار کناری (Sidebar)' },
  { value: 'both', label: 'هر دو' },
];

export default function EnamadSettingsForm({ settings }: IProps) {
  const { t } = useTranslation();
  const { locale } = useRouter();
  const { mutate: updateSettingsMutation, isLoading: loading } =
    useUpdateSettingsMutation();
  const { options } = settings ?? {};

  const {
    register,
    handleSubmit,
    control,
    reset,
    watch,
    formState: { errors, isDirty },
  } = useForm<EnamadFormValues>({
    shouldUnregister: true,
    //@ts-ignore
    resolver: yupResolver(enamadValidationSchema),
    defaultValues: {
      enamad: {
        enabled: options?.enamad?.enabled ?? false,
        code: options?.enamad?.code ?? '',
        link: options?.enamad?.link ?? '',
        displayLocation: options?.enamad?.displayLocation ?? 'footer',
      },
    },
  });

  const enamadEnabled = watch('enamad.enabled');

  async function onSubmit(values: EnamadFormValues) {
    updateSettingsMutation({
      language: locale,
      // @ts-ignore
      options: {
        ...options,
        enamad: {
          ...values.enamad,
        },
      },
    });
    reset(values, { keepValues: true });
  }

  useConfirmRedirectIfDirty({ isDirty });

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base sm:mt-8 sm:mb-3">
        <Description
          title={t('form:enamad-settings-title')}
          details={t('form:enamad-settings-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <Alert
              message={t('form:enamad-alert-info')}
              variant="info"
              closeable={false}
              className="mb-5"
            />
          </div>

          <div className="mb-5">
            <SwitchInput
              name="enamad.enabled"
              control={control}
              label={t('form:input-label-enamad-enabled')}
              toolTipText={t('form:input-tooltip-enamad-enabled')}
            />
          </div>

          {enamadEnabled && (
            <>
              <div className="mb-5">
                <Label>{t('form:input-label-enamad-code')}</Label>
                <TextArea
                  {...register('enamad.code')}
                  placeholder={t('form:input-placeholder-enamad-code')}
                  variant="outline"
                  className="mb-2"
                  rows={6}
                />
                {errors?.enamad?.code && (
                  <p className="my-2 text-xs text-red-500">
                    {t(errors.enamad.code.message as string)}
                  </p>
                )}
                <p className="text-xs text-body">
                  {t('form:input-help-enamad-code')}
                </p>
              </div>

              <Input
                label={t('form:input-label-enamad-link')}
                toolTipText={t('form:input-tooltip-enamad-link')}
                {...register('enamad.link')}
                placeholder="https://trustseal.enamad.ir/..."
                variant="outline"
                className="mb-5"
                error={t(errors?.enamad?.link?.message as string)}
              />

              <div className="mb-5">
                <Label>{t('form:input-label-enamad-display-location')}</Label>
                <SelectInput
                  name="enamad.displayLocation"
                  control={control}
                  options={displayLocationOptions}
                  defaultValue={displayLocationOptions[0]}
                />
                <p className="mt-2 text-xs text-body">
                  {t('form:input-help-enamad-display-location')}
                </p>
              </div>

              <div className="p-4 mb-5 rounded bg-gray-100 dark:bg-gray-800">
                <h4 className="mb-2 text-sm font-semibold">
                  {t('form:enamad-preview-title')}
                </h4>
                <p className="mb-3 text-xs text-body">
                  {t('form:enamad-preview-help')}
                </p>
                {watch('enamad.code') ? (
                  <div
                    className="enamad-preview"
                    dangerouslySetInnerHTML={{ __html: watch('enamad.code') }}
                  />
                ) : (
                  <p className="text-xs text-muted">
                    {t('form:enamad-preview-empty')}
                  </p>
                )}
              </div>
            </>
          )}
        </Card>
      </div>

      <StickyFooterPanel className="z-0">
        <Button
          loading={loading}
          disabled={loading || !Boolean(isDirty)}
          className="text-sm md:text-base"
        >
          <SaveIcon className="relative w-6 h-6 top-px shrink-0 ltr:mr-2 rtl:pl-2" />
          {t('form:button-label-save-settings')}
        </Button>
      </StickyFooterPanel>
    </form>
  );
}
