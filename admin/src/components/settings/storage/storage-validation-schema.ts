import * as yup from 'yup';

export const storageValidationSchema = yup.object().shape({
  storage: yup.object().shape({
    default_driver: yup.string().oneOf(['local', 'telegram', 'google_drive', 'ftp']),
    
    type_mapping: yup.object().shape({
      image: yup.string().oneOf(['local', 'telegram', 'google_drive', 'ftp']),
      video: yup.string().oneOf(['local', 'telegram', 'google_drive', 'ftp']),
      digital_file: yup.string().oneOf(['local', 'telegram', 'google_drive', 'ftp']),
      document: yup.string().oneOf(['local', 'telegram', 'google_drive', 'ftp']),
    }),
    
    token_expiration: yup.object().shape({
      enabled: yup.boolean(),
      default_ttl: yup.number().when('enabled', ([enabled], schema) => {
        if (enabled === true) {
          return schema
            .required('form:error-token-ttl-required')
            .min(60, 'form:error-token-ttl-min')
            .max(31536000, 'form:error-token-ttl-max');
        }
        return schema;
      }),
    }),
    
    drivers: yup.object().shape({
      telegram: yup.object().shape({
        enabled: yup.boolean(),
        api_id: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-telegram-api-id-required');
          }
          return schema;
        }),
        api_hash: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-telegram-api-hash-required');
          }
          return schema;
        }),
        phone: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-telegram-phone-required');
          }
          return schema;
        }),
        channel_id: yup.string(),
      }),
      
      google_drive: yup.object().shape({
        enabled: yup.boolean(),
        client_id: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-google-drive-client-id-required');
          }
          return schema;
        }),
        client_secret: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-google-drive-client-secret-required');
          }
          return schema;
        }),
        refresh_token: yup.string(),
        folder_id: yup.string(),
      }),
      
      ftp: yup.object().shape({
        enabled: yup.boolean(),
        host: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-ftp-host-required');
          }
          return schema;
        }),
        username: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-ftp-username-required');
          }
          return schema;
        }),
        password: yup.string().when('enabled', ([enabled], schema) => {
          if (enabled === true) {
            return schema.required('form:error-ftp-password-required');
          }
          return schema;
        }),
        port: yup.number(),
        ssl: yup.boolean(),
        base_url: yup.string().url('form:error-ftp-url-invalid'),
      }),
    }),
  }),
});
