import * as yup from 'yup';

export const transferShopOwnershipValidationSchema = yup.object({
  shop_id: yup.string().optional(),
  vendor: yup.object({
    id: yup.number().required('form:error-id-required'),
    name: yup.string().required('form:error-name-required'),
  }).required(),
  message: yup.string().optional(),
});