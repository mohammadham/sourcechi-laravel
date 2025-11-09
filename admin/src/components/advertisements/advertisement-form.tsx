import { useForm, FormProvider } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import { advertisementValidationSchema } from './advertisement-validation-schema';
import Card from '@/components/common/card';
import Button from '@/components/ui/button';
import Description from '@/components/ui/description';
import Input from '@/components/ui/input';
import TextArea from '@/components/ui/text-area';
import Label from '@/components/ui/label';
import SelectInput from '@/components/ui/select-input';
import { useTranslation } from 'next-i18next';
import { Advertisement, AdvertisementInput } from '@/data/advertisements';
import { useRouter } from 'next/router';
import { useState, useEffect } from 'react';
import FileInput from '@/components/ui/file-input';
import { usePositionDimensionsQuery } from '@/data/advertisements';
import Alert from '@/components/ui/alert';
import SwitchInput from '@/components/ui/switch-input';
import * as yup from 'yup';

type FormValues = yup.InferType<typeof advertisementValidationSchema>;

type IProps = {
  initialValues?: Advertisement | null;
  onSubmit: (values: AdvertisementInput) => void;
  loading: boolean;
};

const advertisementTypes = [
  { value: 'image', label: 'تصویر (Image)' },
  { value: 'video', label: 'ویدیو (Video)' },
  { value: 'html', label: 'کد HTML/JavaScript' },
];

const advertisementPositions = [
  { value: 'header', label: 'بالای صفحه (Header)' },
  { value: 'sidebar', label: 'نوار کناری (Sidebar)' },
  { value: 'footer', label: 'پایین صفحه (Footer)' },
  { value: 'between_products', label: 'بین محصولات' },
  { value: 'product_detail', label: 'صفحه جزئیات محصول' },
  { value: 'popup', label: 'پنجره بازشو (Popup)' },
];

export default function AdvertisementForm({ initialValues, onSubmit, loading }: IProps) {
  const { t } = useTranslation();
  const router = useRouter();
  const [selectedType, setSelectedType] = useState<string>(initialValues?.type || 'image');
  const [selectedPosition, setSelectedPosition] = useState<string>(initialValues?.position || 'header');
  const { data: dimensionsData } = usePositionDimensionsQuery();

  // Find the initial type and position objects
  const initialTypeObject = advertisementTypes.find(t => t.value === (initialValues?.type || 'image'));
  const initialPositionObject = advertisementPositions.find(p => p.value === (initialValues?.position || 'header'));

  const methods = useForm<FormValues>({
    resolver: yupResolver(advertisementValidationSchema),
    defaultValues: {
      title: initialValues?.title || '',
      type: initialTypeObject || advertisementTypes[0],
      position: initialPositionObject || advertisementPositions[0],
      html_code: initialValues?.html_code || '',
      target_url: initialValues?.target_url || '',
      open_in_new_tab: initialValues?.open_in_new_tab ?? true,
      is_active: initialValues?.is_active ?? true,
      order: initialValues?.order ?? 0,
      display_settings: null,
    },
  });

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    formState: { errors },
  } = methods;

  const watchType = watch('type');
  const watchPosition = watch('position');

  useEffect(() => {
    const typeValue = typeof watchType === 'object' && watchType !== null && 'value' in watchType ? (watchType as any).value : watchType;
    if (typeValue) {
      setSelectedType(typeValue);
    }
  }, [watchType]);

  useEffect(() => {
    const positionValue = typeof watchPosition === 'object' && watchPosition !== null && 'value' in watchPosition ? (watchPosition as any).value : watchPosition;
    if (positionValue) {
      setSelectedPosition(positionValue);
    }
  }, [watchPosition]);

  const getDimensionInfo = () => {
    if (!dimensionsData || !selectedPosition) return null;
    const info = (dimensionsData as any)[selectedPosition];
    if (!info) return null;

    return (
      <Alert
        message="راهنمای ابعاد تبلیغ"
        variant="info"
        closeable={false}
        className="mb-5"
      >
        <div>
          <p className="font-semibold mb-2">{info.description}</p>
          <p className="mb-2">
            <strong>ابعاد توصیه شده:</strong> {info.recommended.width}x{info.recommended.height} پیکسل
          </p>
          {info.alternatives && info.alternatives.length > 0 && (
            <div>
              <strong>ابعاد جایگزین:</strong>
              <ul className="list-disc list-inside mt-1">
                {info.alternatives.map((alt: any, index: number) => (
                  <li key={index}>
                    {alt.width}x{alt.height} پیکسل
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </Alert>
    );
  };

 
  const handleFormSubmit = (values: FormValues) => {
    // Extract values from objects if they are select options
    const typeValue = typeof values.type === 'object' ? (values.type as any).value : values.type;
    const positionValue = typeof values.position === 'object' ? (values.position as any).value : values.position;

    const input: AdvertisementInput = {
      title: values.title,
      type: typeValue,
      position: positionValue,
      target_url: values.target_url || undefined,
      open_in_new_tab: values.open_in_new_tab,
      is_active: values.is_active,
      order: values.order,
    };

    if (typeValue === 'html') {
      input.html_code = values.html_code;
    } else if (values.media) {
      // Media can be either an Attachment object (from Uploader) or a File
      input.media = values.media;
    }

    onSubmit(input);
  };

  return (
    <FormProvider {...methods}>
      <form onSubmit={handleSubmit(handleFormSubmit)} noValidate>
        <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base sm:my-8">
          <Description
            title={t('form:advertisement-info')}
            details={t('form:advertisement-info-help-text')}
            className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pe-4 md:w-1/3 md:pe-5"
          />

          <Card className="w-full sm:w-8/12 md:w-2/3">
            <Input
              label={t('form:input-label-title')}
              {...register('title')}
              error={t(errors.title?.message!)}
              variant="outline"
              className="mb-5"
              required
            />

            <div className="mb-5">
              <Label>{t('form:input-label-type')} *</Label>
              <SelectInput
                name="type"
                control={control}
                options={advertisementTypes}
                getOptionLabel={(option: any) => option.label}
                getOptionValue={(option: any) => option.value}
              />
              {errors.type && (
                <p className="my-2 text-xs text-red-500">{t(errors.type?.message!)}</p>
              )}
            </div>

            <div className="mb-5">
              <Label>{t('form:input-label-position')} *</Label>
              <SelectInput
                name="position"
                control={control}
                options={advertisementPositions}
                getOptionLabel={(option: any) => option.label}
                getOptionValue={(option: any) => option.value}
              />
              {errors.position && (
                <p className="my-2 text-xs text-red-500">{t(errors.position?.message!)}</p>
              )}
            </div>

            {getDimensionInfo()}

            {(selectedType === 'image' || selectedType === 'video') && (
              <div className="mb-5">
                <Label>
                  {selectedType === 'image' ? t('form:input-label-image') : t('form:input-label-video')} {!initialValues && '*'}
                </Label>
                <FileInput
                  name="media"
                  control={control}
                  multiple={false}
                  acceptFile={selectedType === 'video'}
                />
                <p className="text-xs text-body mt-2">
                  {selectedType === 'image'
                    ? 'فرمت‌های مجاز: JPG, PNG, GIF, WebP - حداکثر 5MB'
                    : 'فرمت‌های مجاز: MP4, WebM, OGG - حداکثر 50MB'}
                </p>
                {errors.media && (
                  <p className="my-2 text-xs text-red-500">{t(errors.media?.message!)}</p>
                )}
              </div>
            )}

            {selectedType === 'html' && (
              <div className="mb-5">
                <Label>{t('form:input-label-html-code')} *</Label>
                <TextArea
                  {...register('html_code')}
                  variant="outline"
                  className="mb-2"
                  rows={8}
                  placeholder="<div>...</div> or <script>...</script>"
                />
                {errors.html_code && (
                  <p className="my-2 text-xs text-red-500">{t(errors.html_code?.message!)}</p>
                )}
                <p className="text-xs text-body">
                  کد HTML یا JavaScript خود را وارد کنید. این کد به صورت مستقیم در صفحه اجرا خواهد شد.
                </p>
              </div>
            )}

            <Input
              label={t('form:input-label-target-url')}
              {...register('target_url')}
              error={t(errors.target_url?.message!)}
              variant="outline"
              className="mb-5"
              placeholder="https://example.com"
              toolTipText="لینک مقصد هنگام کلیک روی تبلیغ"
            />

            <div className="mb-5">
              <SwitchInput
                name="open_in_new_tab"
                control={control}
                label={t('form:input-label-open-in-new-tab')}
              />
            </div>

            <div className="mb-5">
              <SwitchInput
                name="is_active"
                control={control}
                label={t('form:input-label-active')}
              />
            </div>

            <Input
              label={t('form:input-label-order')}
              {...register('order')}
              type="number"
              error={t(errors.order?.message!)}
              variant="outline"
              toolTipText="ترتیب نمایش (عدد کمتر = اولویت بیشتر)"
            />
          </Card>
        </div>

        <div className="mb-5 text-end">
          <Button loading={loading} disabled={loading}>
            {initialValues ? t('form:button-label-update') : t('form:button-label-create')}
          </Button>
        </div>
      </form>
    </FormProvider>
  );
}