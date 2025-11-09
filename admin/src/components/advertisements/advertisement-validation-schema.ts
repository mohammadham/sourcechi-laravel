import * as yup from 'yup';

export const advertisementValidationSchema = yup.object().shape({
  title: yup.string().required('form:error-title-required'),
  type: yup
    .mixed()
    .test('is-valid-type', 'form:error-invalid-type', (value: any) => {
      if (!value) return false;
      const typeValue = typeof value === 'object' ? value.value : value;
      return ['image', 'video', 'html'].includes(typeValue);
    })
    .required('form:error-type-required'),
  position: yup
    .mixed()
    .test('is-valid-position', 'form:error-invalid-position', (value: any) => {
      if (!value) return false;
      const positionValue = typeof value === 'object' ? value.value : value;
      return [
        'header',
        'sidebar',
        'footer',
        'between_products',
        'product_detail',
        'popup',
      ].includes(positionValue);
    })
    .required('form:error-position-required'),
  media: yup.mixed().when('type', ([type], schema) => {
    const typeValue = typeof type === 'object' ? type?.value : type;
    if (typeValue === 'image' || typeValue === 'video') {
      // Media is required for image/video types
      return schema.required('form:error-media-required');
    }
    return schema.nullable();
  }),
  html_code: yup.string().when('type', ([type], schema) => {
    const typeValue = typeof type === 'object' ? type?.value : type;
    if (typeValue === 'html') {
      return schema.required('form:error-html-code-required');
    }
    return schema;
  }),
  target_url: yup.string().url('form:error-invalid-url').nullable(),
  open_in_new_tab: yup.boolean(),
  is_active: yup.boolean(),
  order: yup.number().min(0, 'form:error-order-min').integer(),
  display_settings: yup.object().nullable(),
});
