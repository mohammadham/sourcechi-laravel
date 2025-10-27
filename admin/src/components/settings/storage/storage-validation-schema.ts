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
    
    drivers: yup.object().shape({
      telegram: yup.object().shape({
        enabled: yup.boolean(),
        api_id: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-telegram-api-id-required'),
        }),
        api_hash: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-telegram-api-hash-required'),
        }),
        phone: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-telegram-phone-required'),
        }),
        channel_id: yup.string(),
      }),
      
      google_drive: yup.object().shape({
        enabled: yup.boolean(),
        client_id: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-google-drive-client-id-required'),
        }),
        client_secret: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-google-drive-client-secret-required'),
        }),
        refresh_token: yup.string(),
        folder_id: yup.string(),
      }),
      
      ftp: yup.object().shape({
        enabled: yup.boolean(),
        host: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-ftp-host-required'),
        }),
        username: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-ftp-username-required'),
        }),
        password: yup.string().when('enabled', {
          is: true,
          then: yup.string().required('form:error-ftp-password-required'),
        }),
        port: yup.number(),
        ssl: yup.boolean(),
        base_url: yup.string().url('form:error-ftp-url-invalid'),
      }),
    }),
  }),
});
