import * as yup from 'yup';

export const enamadValidationSchema = yup.object().shape({
  enamad: yup.object().shape({
    enabled: yup.boolean(),
    code: yup.string().when('enabled', {
      is: true,
      then: yup.string().required('form:error-enamad-code-required'),
    }),
    link: yup.string().url('form:error-enamad-link-invalid'),
    displayLocation: yup
      .string()
      .oneOf(['footer', 'sidebar', 'both'], 'form:error-enamad-location-invalid'),
  }),
});
