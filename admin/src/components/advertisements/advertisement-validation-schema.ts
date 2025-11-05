import * as yup from 'yup';

export const advertisementValidationSchema = yup.object().shape({
  title: yup.string().required('form:error-title-required'),
  type: yup
    .string()
    .oneOf(['image', 'video', 'html'], 'form:error-invalid-type')
    .required('form:error-type-required'),
  position: yup
    .string()
    .oneOf(
      [
        'header',
        'sidebar',
        'footer',
        'between_products',
        'product_detail',
        'popup',
      ],
      'form:error-invalid-position'
    )
    .required('form:error-position-required'),
  media: yup.mixed().when('type', ([type], schema) => {
    if (type === 'image' || type === 'video') {
      return schema.nullable();
    }
    return schema;
  }),
  html_code: yup.string().when('type', ([type], schema) => {
    if (type === 'html') {
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
