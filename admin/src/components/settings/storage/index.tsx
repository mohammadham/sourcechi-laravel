import Card from '@/components/common/card';
import Button from '@/components/ui/button';
import Description from '@/components/ui/description';
import Input from '@/components/ui/input';
import Label from '@/components/ui/label';
import SelectInput from '@/components/ui/select-input';
import StickyFooterPanel from '@/components/ui/sticky-footer-panel';
import SwitchInput from '@/components/ui/switch-input';
import { useUpdateSettingsMutation } from '@/data/settings';
import { Settings } from '@/types';
import { yupResolver } from '@hookform/resolvers/yup';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { useForm, Controller } from 'react-hook-form';
import { storageValidationSchema } from './storage-validation-schema';
import Select from '@/components/ui/select/select';
import { SaveIcon } from '@/components/icons/save';
import { useConfirmRedirectIfDirty } from '@/utils/confirmed-redirect-if-dirty';
import Alert from '@/components/ui/alert';
import { useState, useMemo, useEffect } from 'react';
import axios from 'axios';

// Create axios instance with baseURL
const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_REST_API_ENDPOINT,
  timeout: 50000,
  headers: {
    'Content-Type': 'application/json',
  },
});

type StorageFormValues = {
  storage: {
    default_driver: string;
    type_mapping: {
      image: string;
      video: string;
      digital_file: string;
      document: string;
    };
    drivers: {
      local: { enabled: boolean };
      telegram: {
        enabled: boolean;
        api_id: string;
        api_hash: string;
        phone: string;
        channel_id: string;
      };
      google_drive: {
        enabled: boolean;
        client_id: string;
        client_secret: string;
        refresh_token: string;
        folder_id: string;
        redirect_uri: string;
      };
      ftp: {
        enabled: boolean;
        host: string;
        username: string;
        password: string;
        port: number;
        root: string;
        ssl: boolean;
        timeout: number;
        passive: boolean;
        base_url: string;
      };
    };
  };
};

type IProps = {
  settings?: Settings | null;
};

const allDriverOptions = [
  { value: 'local', label: 'Local Storage' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'google_drive', label: 'Google Drive' },
  { value: 'ftp', label: 'FTP' },
];

export default function StorageSettingsForm({ settings }: IProps) {
  const { t } = useTranslation();
  const { locale } = useRouter();
  const { mutate: updateSettingsMutation, isLoading: loading } =
    useUpdateSettingsMutation();
  const { options } = settings ?? {};

  const [testingDriver, setTestingDriver] = useState<string | null>(null);
  const [testResult, setTestResult] = useState<any>(null);
  
  // Telegram authentication states
  const [telegramAuthStep, setTelegramAuthStep] = useState<'idle' | 'phone' | 'code' | '2fa' | 'authenticated'>('idle');
  const [telegramAuthData, setTelegramAuthData] = useState<any>(null);
  const [telegramPhone, setTelegramPhone] = useState<string>('');
  const [telegramCode, setTelegramCode] = useState<string>('');
  const [telegram2FA, setTelegram2FA] = useState<string>('');
  const [telegramAuthError, setTelegramAuthError] = useState<string>('');
  const [authCheckDone, setAuthCheckDone] = useState<boolean>(false);

  const {
    register,
    handleSubmit,
    control,
    reset,
    watch,
    formState: { errors, isDirty },
  } = useForm<StorageFormValues>({
    shouldUnregister: true,
    //@ts-ignore
    resolver: yupResolver(storageValidationSchema),
    defaultValues: {
      storage: {
        default_driver: options?.storage?.default_driver ?? 'local',
        type_mapping: {
          image: options?.storage?.type_mapping?.image ?? 'local',
          video: options?.storage?.type_mapping?.video ?? 'local',
          digital_file: options?.storage?.type_mapping?.digital_file ?? 'local',
          document: options?.storage?.type_mapping?.document ?? 'local',
        },
        drivers: {
          local: { enabled: true },
          telegram: {
            enabled: options?.storage?.drivers?.telegram?.enabled ?? false,
            api_id: options?.storage?.drivers?.telegram?.api_id ?? '',
            api_hash: options?.storage?.drivers?.telegram?.api_hash ?? '',
            phone: options?.storage?.drivers?.telegram?.phone ?? '',
            channel_id: options?.storage?.drivers?.telegram?.channel_id ?? '',
          },
          google_drive: {
            enabled: options?.storage?.drivers?.google_drive?.enabled ?? false,
            client_id: options?.storage?.drivers?.google_drive?.client_id ?? '',
            client_secret: options?.storage?.drivers?.google_drive?.client_secret ?? '',
            refresh_token: options?.storage?.drivers?.google_drive?.refresh_token ?? '',
            folder_id: options?.storage?.drivers?.google_drive?.folder_id ?? 'root',
            redirect_uri: options?.storage?.drivers?.google_drive?.redirect_uri ?? '',
          },
          ftp: {
            enabled: options?.storage?.drivers?.ftp?.enabled ?? false,
            host: options?.storage?.drivers?.ftp?.host ?? '',
            username: options?.storage?.drivers?.ftp?.username ?? '',
            password: options?.storage?.drivers?.ftp?.password ?? '',
            port: options?.storage?.drivers?.ftp?.port ?? 21,
            root: options?.storage?.drivers?.ftp?.root ?? '/',
            ssl: options?.storage?.drivers?.ftp?.ssl ?? false,
            timeout: options?.storage?.drivers?.ftp?.timeout ?? 30,
            passive: options?.storage?.drivers?.ftp?.passive ?? true,
            base_url: options?.storage?.drivers?.ftp?.base_url ?? '',
          },
        },
      },
    },
  });

  const telegramEnabled = watch('storage.drivers.telegram.enabled');
  const googleDriveEnabled = watch('storage.drivers.google_drive.enabled');
  const ftpEnabled = watch('storage.drivers.ftp.enabled');
  
  // Watch telegram credentials for checking auth status
  const telegramApiId = watch('storage.drivers.telegram.api_id');
  const telegramApiHash = watch('storage.drivers.telegram.api_hash');
  const telegramPhoneWatch = watch('storage.drivers.telegram.phone');
  
  // Check telegram authentication status ONLY ONCE on mount
  useEffect(() => {
    const checkTelegramAuth = async () => {
      // Only check if enabled and has credentials and hasn't been checked yet
      if (!telegramEnabled || !telegramApiId || !telegramApiHash || !telegramPhoneWatch || authCheckDone) {
        return;
      }
      
      console.log('[Telegram] Checking authentication status (one-time check)...');
      setAuthCheckDone(true); // Mark as checked to prevent multiple calls
      
      try {
        const response = await apiClient.post('/api/storage/telegram/auth/check', {
          phone: telegramPhoneWatch,
          api_id: telegramApiId,
          api_hash: telegramApiHash,
        });
        
        console.log('[Telegram] Auth status response:', response.data);
        
        if (response.data.success && response.data.authenticated) {
          console.log('[Telegram] User is authenticated');
          setTelegramAuthStep('authenticated');
          setTelegramPhone(telegramPhoneWatch);
        } else {
          console.log('[Telegram] User is not authenticated');
          setTelegramAuthStep('idle');
        }
      } catch (error: any) {
        console.error('[Telegram] Auth check error:', error);
        setTelegramAuthStep('idle');
      }
    };
    
    // Only run once when component mounts and credentials are available
    checkTelegramAuth();
  }, [telegramEnabled]); // Only depend on telegramEnabled to run once when it becomes true
  
  // Filter driver options - SIMPLIFIED: show enabled drivers only
  // Local is always enabled by default
  const driverOptions = useMemo(() => {
    console.log('[Driver Options] Building driver options...', {
      telegram: telegramEnabled,
      google_drive: googleDriveEnabled,
      ftp: ftpEnabled,
    });

    const options = [
      allDriverOptions[0], // local - always available
    ];
    
    if (telegramEnabled) {
      options.push(allDriverOptions[1]); // telegram
    }
    if (googleDriveEnabled) {
      options.push(allDriverOptions[2]); // google_drive
    }
    if (ftpEnabled) {
      options.push(allDriverOptions[3]); // ftp
    }
    
    console.log('[Driver Options] Available drivers:', options.map(o => o.value));
    return options;
  }, [telegramEnabled, googleDriveEnabled, ftpEnabled]);

  async function onSubmit(values: StorageFormValues) {
    updateSettingsMutation({
      language: locale,
      // @ts-ignore
      options: {
        ...options,
        storage: values.storage,
      },
    });
    reset(values, { keepValues: true });
  }

  // Telegram authentication handlers
  const handleTelegramStartAuth = async () => {
    console.log('[Telegram Auth] Starting authentication process...');
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    const apiId = watch('storage.drivers.telegram.api_id');
    const apiHash = watch('storage.drivers.telegram.api_hash');
    const phone = watch('storage.drivers.telegram.phone');

    console.log('[Telegram Auth] Credentials check:', {
      hasApiId: !!apiId,
      hasApiHash: !!apiHash,
      hasPhone: !!phone,
      phone: phone ? `${phone.substring(0, 4)}****` : 'N/A',
    });

    if (!apiId || !apiHash || !phone) {
      console.error('[Telegram Auth] Missing required credentials');
      setTestResult({
        success: false,
        message: t('form:error-telegram-credentials-required'),
      });
      setTestingDriver(null);
      return;
    }

    try {
      console.log('[Telegram Auth] Sending auth request to backend...', {
        endpoint: `${process.env.NEXT_PUBLIC_REST_API_ENDPOINT}/api/storage/telegram/auth/start`,
      });

      const response = await apiClient.post('/api/storage/telegram/auth/start', {
        phone,
        api_id: apiId,
        api_hash: apiHash,
      });

      console.log('[Telegram Auth] Backend response:', {
        success: response.data.success,
        hasData: !!response.data.data,
      });

      if (response.data.success) {
        // Check if already authenticated
        if (response.data.authenticated) {
          console.log('[Telegram Auth] Already authenticated, skipping to authenticated state');
          setTelegramAuthStep('authenticated');
          setTelegramPhone(phone);
          setTestResult({
            success: true,
            message: t('form:telegram-already-authenticated'),
          });
        } else {
          console.log('[Telegram Auth] Code sent successfully, moving to code verification step');
          setTelegramAuthStep('code');
          setTelegramPhone(phone);
          setTelegramAuthData(response.data);
          setTestResult({
            success: true,
            message: t('form:telegram-code-sent'),
          });
        }
      } else {
        console.error('[Telegram Auth] Failed:', response.data.message);
        setTestResult({
          success: false,
          message: response.data.message,
        });
      }
    } catch (error: any) {
      console.error('[Telegram Auth] Error occurred:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });
      
      const errorMessage = error.response?.data?.message || error.message || t('form:error-telegram-auth-failed');
      setTestResult({
        success: false,
        message: `خطا: ${errorMessage}`,
      });
    } finally {
      setTestingDriver(null);
      console.log('[Telegram Auth] Authentication process completed');
    }
  };

  const handleTelegramVerifyCode = async () => {
    console.log('[Telegram Verify] Starting code verification...');
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    if (!telegramCode) {
      console.error('[Telegram Verify] Code is empty');
      setTelegramAuthError(t('form:error-code-required'));
      setTestingDriver(null);
      return;
    }

    console.log('[Telegram Verify] Verifying code:', {
      codeLength: telegramCode.length,
      phone: telegramPhone ? `${telegramPhone.substring(0, 4)}****` : 'N/A',
    });

    try {
      const response = await apiClient.post('/api/storage/telegram/auth/verify', {
        phone: telegramPhone,
        code: telegramCode,
      });

      // Check both possible locations for requires_2fa flag
      const requires2FA = response.data.data?.requires_2fa || response.data.requires_2fa;
      
      console.log('[Telegram Verify] Backend response:', {
        success: response.data.success,
        requires2FA: requires2FA,
        responseData: response.data.data,
        fullResponse: response.data,
      });

      if (response.data.success) {
        if (requires2FA) {
          console.log('[Telegram Verify] 2FA required, moving to 2FA step');
          setTelegramAuthStep('2fa');
          setTelegramCode(''); // Clear the code field
          setTestResult({
            success: true,
            message: t('form:telegram-2fa-required'),
          });
        } else {
          console.log('[Telegram Verify] Authentication successful');
          setTelegramAuthStep('authenticated');
          setTestResult({
            success: true,
            message: t('form:telegram-authenticated-successfully'),
          });
        }
      } else {
        console.error('[Telegram Verify] Verification failed:', response.data.message);
        setTelegramAuthError(response.data.message);
      }
    } catch (error: any) {
      console.error('[Telegram Verify] Error occurred:', {
        message: error.message,
        response: error.response?.data,
      });
      setTelegramAuthError(error.response?.data?.message || t('form:error-verification-failed'));
    } finally {
      setTestingDriver(null);
      console.log('[Telegram Verify] Code verification completed');
    }
  };

  const handleTelegramVerify2FA = async () => {
    console.log('[Telegram 2FA] Starting 2FA verification...');
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    if (!telegram2FA) {
      console.error('[Telegram 2FA] Password is empty');
      setTelegramAuthError(t('form:error-2fa-required'));
      setTestingDriver(null);
      return;
    }

    console.log('[Telegram 2FA] Verifying 2FA password');

    try {
      const response = await apiClient.post('/api/storage/telegram/auth/2fa', {
        phone: telegramPhone,
        password: telegram2FA,
      });

      console.log('[Telegram 2FA] Backend response:', {
        success: response.data.success,
      });

      if (response.data.success) {
        console.log('[Telegram 2FA] 2FA verification successful');
        setTelegramAuthStep('authenticated');
        setTestResult({
          success: true,
          message: t('form:telegram-authenticated-successfully'),
        });
      } else {
        console.error('[Telegram 2FA] Verification failed:', response.data.message);
        setTelegramAuthError(response.data.message);
      }
    } catch (error: any) {
      console.error('[Telegram 2FA] Error occurred:', {
        message: error.message,
        response: error.response?.data,
      });
      setTelegramAuthError(error.response?.data?.message || t('form:error-2fa-verification-failed'));
    } finally {
      setTestingDriver(null);
      console.log('[Telegram 2FA] 2FA verification completed');
    }
  };

  const handleTelegramTestChannel = async () => {
    console.log('[Telegram Channel Test] Starting channel test...');
    setTestingDriver('telegram');
    setTestResult(null);

    const apiId = watch('storage.drivers.telegram.api_id');
    const apiHash = watch('storage.drivers.telegram.api_hash');
    const phone = watch('storage.drivers.telegram.phone');
    const channelId = watch('storage.drivers.telegram.channel_id');

    console.log('[Telegram Channel Test] Channel info:', {
      channelId: channelId || 'N/A',
      hasApiId: !!apiId,
      hasApiHash: !!apiHash,
      hasPhone: !!phone,
    });

    if (!channelId) {
      console.error('[Telegram Channel Test] Channel ID is missing');
      setTestResult({
        success: false,
        message: t('form:error-channel-id-required'),
      });
      setTestingDriver(null);
      return;
    }

    try {
      console.log('[Telegram Channel Test] Sending test request to backend...');
      const response = await apiClient.post('/api/storage/telegram/test-channel', {
        api_id: apiId,
        api_hash: apiHash,
        phone,
        channel_id: channelId,
      });

      console.log('[Telegram Channel Test] Backend response:', {
        success: response.data.success,
        message: response.data.message,
      });

      setTestResult(response.data);
    } catch (error: any) {
      console.error('[Telegram Channel Test] Error occurred:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });
      
      setTestResult({
        success: false,
        message: error.response?.data?.message || t('form:error-channel-test-failed'),
      });
    } finally {
      setTestingDriver(null);
      console.log('[Telegram Channel Test] Channel test completed');
    }
  };

  const handleTelegramAuthFlow = async () => {
    if (telegramAuthStep === 'idle') {
      await handleTelegramStartAuth();
    } else if (telegramAuthStep === 'code') {
      await handleTelegramVerifyCode();
    } else if (telegramAuthStep === '2fa') {
      await handleTelegramVerify2FA();
    } else if (telegramAuthStep === 'authenticated') {
      await handleTelegramTestChannel();
    }
  };

  const testDriver = async (driverName: string) => {
    setTestingDriver(driverName);
    setTestResult(null);

    try {
      const response = await apiClient.post('/api/storage/test', {
        driver: driverName,
      });
      setTestResult(response.data);
    } catch (error: any) {
      setTestResult({
        success: false,
        message: error.response?.data?.message || 'Test failed',
      });
    } finally {
      setTestingDriver(null);
    }
  };

  useConfirmRedirectIfDirty({ isDirty });

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      {/* Default Driver Selection */}
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base sm:mt-8 sm:mb-3">
        <Description
          title={t('form:storage-default-driver-title')}
          details={t('form:storage-default-driver-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <Label>{t('form:input-label-default-storage-driver')}</Label>
            <Controller
              name="storage.default_driver"
              control={control}
              render={({ field }) => {
                // Ensure the value is always valid
                const currentValue = field.value;
                const isValueValid = driverOptions.some((opt: any) => opt.value === currentValue);
                const safeValue = isValueValid ? currentValue : 'local';
                
                // Update form if value was invalid
                if (!isValueValid && currentValue !== 'local') {
                  console.log('[Driver Select] Invalid value detected, resetting to local:', currentValue);
                  field.onChange('local');
                }
                
                const selectedOption = driverOptions.find((opt: any) => opt.value === safeValue);
                
                return (
                  <Select
                    {...field}
                    value={selectedOption}
                    onChange={(option: any) => {
                      console.log('[Driver Select] default_driver changed:', option?.value);
                      field.onChange(option?.value || 'local');
                    }}
                    options={driverOptions}
                    getOptionLabel={(option: any) => option?.label || ''}
                    getOptionValue={(option: any) => option?.value || ''}
                    placeholder={t('form:select-driver-placeholder')}
                  />
                );
              }}
            />
          </div>
        </Card>
      </div>

      {/* Type Mapping */}
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base">
        <Description
          title={t('form:storage-type-mapping-title')}
          details={t('form:storage-type-mapping-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <Label>{t('form:input-label-storage-image-driver')}</Label>
            <Controller
              name="storage.type_mapping.image"
              control={control}
              render={({ field }) => {
                const currentValue = field.value;
                const isValueValid = driverOptions.some((opt: any) => opt.value === currentValue);
                const safeValue = isValueValid ? currentValue : 'local';
                
                if (!isValueValid && currentValue !== 'local') {
                  console.log('[Driver Select] Invalid image driver, resetting to local:', currentValue);
                  field.onChange('local');
                }
                
                const selectedOption = driverOptions.find((opt: any) => opt.value === safeValue);
                
                return (
                  <Select
                    {...field}
                    value={selectedOption}
                    onChange={(option: any) => {
                      console.log('[Driver Select] image driver changed:', option?.value);
                      field.onChange(option?.value || 'local');
                    }}
                    options={driverOptions}
                    getOptionLabel={(option: any) => option?.label || ''}
                    getOptionValue={(option: any) => option?.value || ''}
                    placeholder={t('form:select-driver-placeholder')}
                  />
                );
              }}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-video-driver')}</Label>
            <Controller
              name="storage.type_mapping.video"
              control={control}
              render={({ field }) => {
                const currentValue = field.value;
                const isValueValid = driverOptions.some((opt: any) => opt.value === currentValue);
                const safeValue = isValueValid ? currentValue : 'local';
                
                if (!isValueValid && currentValue !== 'local') {
                  console.log('[Driver Select] Invalid video driver, resetting to local:', currentValue);
                  field.onChange('local');
                }
                
                const selectedOption = driverOptions.find((opt: any) => opt.value === safeValue);
                
                return (
                  <Select
                    {...field}
                    value={selectedOption}
                    onChange={(option: any) => {
                      console.log('[Driver Select] video driver changed:', option?.value);
                      field.onChange(option?.value || 'local');
                    }}
                    options={driverOptions}
                    getOptionLabel={(option: any) => option?.label || ''}
                    getOptionValue={(option: any) => option?.value || ''}
                    placeholder={t('form:select-driver-placeholder')}
                  />
                );
              }}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-digital-file-driver')}</Label>
            <Controller
              name="storage.type_mapping.digital_file"
              control={control}
              render={({ field }) => {
                const currentValue = field.value;
                const isValueValid = driverOptions.some((opt: any) => opt.value === currentValue);
                const safeValue = isValueValid ? currentValue : 'local';
                
                if (!isValueValid && currentValue !== 'local') {
                  console.log('[Driver Select] Invalid digital_file driver, resetting to local:', currentValue);
                  field.onChange('local');
                }
                
                const selectedOption = driverOptions.find((opt: any) => opt.value === safeValue);
                
                return (
                  <Select
                    {...field}
                    value={selectedOption}
                    onChange={(option: any) => {
                      console.log('[Driver Select] digital_file driver changed:', option?.value);
                      field.onChange(option?.value || 'local');
                    }}
                    options={driverOptions}
                    getOptionLabel={(option: any) => option?.label || ''}
                    getOptionValue={(option: any) => option?.value || ''}
                    placeholder={t('form:select-driver-placeholder')}
                  />
                );
              }}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-document-driver')}</Label>
            <Controller
              name="storage.type_mapping.document"
              control={control}
              render={({ field }) => {
                const currentValue = field.value;
                const isValueValid = driverOptions.some((opt: any) => opt.value === currentValue);
                const safeValue = isValueValid ? currentValue : 'local';
                
                if (!isValueValid && currentValue !== 'local') {
                  console.log('[Driver Select] Invalid document driver, resetting to local:', currentValue);
                  field.onChange('local');
                }
                
                const selectedOption = driverOptions.find((opt: any) => opt.value === safeValue);
                
                return (
                  <Select
                    {...field}
                    value={selectedOption}
                    onChange={(option: any) => {
                      console.log('[Driver Select] document driver changed:', option?.value);
                      field.onChange(option?.value || 'local');
                    }}
                    options={driverOptions}
                    getOptionLabel={(option: any) => option?.label || ''}
                    getOptionValue={(option: any) => option?.value || ''}
                    placeholder={t('form:select-driver-placeholder')}
                  />
                );
              }}
            />
          </div>
        </Card>
      </div>

      {/* Telegram Driver */}
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base">
        <Description
          title={t('form:storage-telegram-title')}
          details={t('form:storage-telegram-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <SwitchInput
              name="storage.drivers.telegram.enabled"
              control={control}
              label={t('form:input-label-telegram-enabled')}
            />
          </div>

          {telegramEnabled && (
            <>
              <Alert
                message={t('form:telegram-storage-alert-info')}
                variant="info"
                closeable={false}
                className="mb-5"
              />

              <Input
                label={t('form:input-label-telegram-api-id')}
                {...register('storage.drivers.telegram.api_id')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.telegram?.api_id?.message as string)}
              />

              <Input
                label={t('form:input-label-telegram-api-hash')}
                {...register('storage.drivers.telegram.api_hash')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.telegram?.api_hash?.message as string)}
              />

              <Input
                label={t('form:input-label-telegram-phone')}
                {...register('storage.drivers.telegram.phone')}
                placeholder="+989123456789"
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.telegram?.phone?.message as string)}
              />

              <Input
                label={t('form:input-label-telegram-channel-id')}
                {...register('storage.drivers.telegram.channel_id')}
                placeholder="@channel_username or -100XXXXXXXXXX"
                variant="outline"
                className="mb-5"
              />

              {/* Telegram Authentication Flow */}
              <div className="mb-5">
                <Card className="p-4 bg-gray-50">
                  <h4 className="text-sm font-semibold mb-3">
                    {t('form:telegram-authentication-title')}
                  </h4>

                  {telegramAuthStep === 'idle' && (
                    <div>
                      <p className="text-sm text-gray-600 mb-3">
                        {t('form:telegram-auth-step-1-description')}
                      </p>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={handleTelegramStartAuth}
                        loading={testingDriver === 'telegram'}
                        disabled={testingDriver !== null}
                      >
                        {t('form:button-telegram-start-auth')}
                      </Button>
                    </div>
                  )}

                  {telegramAuthStep === 'code' && (
                    <div>
                      <p className="text-sm text-gray-600 mb-3">
                        {t('form:telegram-auth-step-2-description')}
                      </p>
                      <Input
                        name="telegram_code"
                        label={t('form:input-label-telegram-code')}
                        value={telegramCode}
                        onChange={(e) => setTelegramCode(e.target.value)}
                        placeholder="12345"
                        variant="outline"
                        className="mb-3"
                        error={telegramAuthError}
                      />
                      <div className="flex gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          onClick={handleTelegramVerifyCode}
                          loading={testingDriver === 'telegram'}
                          disabled={testingDriver !== null}
                        >
                          {t('form:button-verify-code')}
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => {
                            setTelegramAuthStep('idle');
                            setTelegramCode('');
                            setTelegramAuthError('');
                          }}
                          disabled={testingDriver !== null}
                        >
                          {t('common:button-cancel')}
                        </Button>
                      </div>
                    </div>
                  )}

                  {telegramAuthStep === '2fa' && (
                    <div>
                      <p className="text-sm text-gray-600 mb-3">
                        {t('form:telegram-auth-step-3-description')}
                      </p>
                      <Input
                        name="telegram_2fa_password"
                        label={t('form:input-label-telegram-2fa-password')}
                        type="password"
                        value={telegram2FA}
                        onChange={(e) => setTelegram2FA(e.target.value)}
                        placeholder="••••••••"
                        variant="outline"
                        className="mb-3"
                        error={telegramAuthError}
                      />
                      <div className="flex gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          onClick={handleTelegramVerify2FA}
                          loading={testingDriver === 'telegram'}
                          disabled={testingDriver !== null}
                        >
                          {t('form:button-verify-2fa')}
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => {
                            setTelegramAuthStep('idle');
                            setTelegram2FA('');
                            setTelegramAuthError('');
                          }}
                          disabled={testingDriver !== null}
                        >
                          {t('common:button-cancel')}
                        </Button>
                      </div>
                    </div>
                  )}

                  {telegramAuthStep === 'authenticated' && (
                    <div>
                      <Alert
                        message={t('form:telegram-authenticated-status')}
                        variant="success"
                        closeable={false}
                        className="mb-3"
                      />
                      <div className="flex gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          onClick={handleTelegramTestChannel}
                          loading={testingDriver === 'telegram'}
                          disabled={testingDriver !== null}
                        >
                          {t('form:button-test-channel')}
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          onClick={() => {
                            setTelegramAuthStep('idle');
                            setTelegramCode('');
                            setTelegram2FA('');
                            setTelegramAuthError('');
                            setTestResult(null);
                          }}
                          disabled={testingDriver !== null}
                        >
                          {t('form:button-reset-auth')}
                        </Button>
                      </div>
                    </div>
                  )}
                </Card>
              </div>
            </>
          )}
        </Card>
      </div>

      {/* Google Drive Driver */}
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base">
        <Description
          title={t('form:storage-google-drive-title')}
          details={t('form:storage-google-drive-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <SwitchInput
              name="storage.drivers.google_drive.enabled"
              control={control}
              label={t('form:input-label-google-drive-enabled')}
            />
          </div>

          {googleDriveEnabled && (
            <>
              <Input
                label={t('form:input-label-google-drive-client-id')}
                {...register('storage.drivers.google_drive.client_id')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.google_drive?.client_id?.message as string)}
              />

              <Input
                label={t('form:input-label-google-drive-client-secret')}
                {...register('storage.drivers.google_drive.client_secret')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.google_drive?.client_secret?.message as string)}
              />

              <Input
                label={t('form:input-label-google-drive-refresh-token')}
                {...register('storage.drivers.google_drive.refresh_token')}
                variant="outline"
                className="mb-5"
              />

              <Input
                label={t('form:input-label-google-drive-folder-id')}
                {...register('storage.drivers.google_drive.folder_id')}
                variant="outline"
                className="mb-5"
              />

              <div className="mb-5">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => testDriver('google_drive')}
                  loading={testingDriver === 'google_drive'}
                  disabled={testingDriver !== null}
                >
                  {t('form:button-test-connection')}
                </Button>
              </div>
            </>
          )}
        </Card>
      </div>

      {/* FTP Driver */}
      <div className="flex flex-wrap pb-8 my-5 border-b border-dashed border-border-base">
        <Description
          title={t('form:storage-ftp-title')}
          details={t('form:storage-ftp-help-text')}
          className="w-full px-0 pb-5 sm:w-4/12 sm:py-8 sm:pr-4 md:w-1/3 md:pr-5"
        />

        <Card className="w-full sm:w-8/12 md:w-2/3">
          <div className="mb-5">
            <SwitchInput
              name="storage.drivers.ftp.enabled"
              control={control}
              label={t('form:input-label-ftp-enabled')}
            />
          </div>

          {ftpEnabled && (
            <>
              <Input
                label={t('form:input-label-ftp-host')}
                {...register('storage.drivers.ftp.host')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.ftp?.host?.message as string)}
              />

              <Input
                label={t('form:input-label-ftp-username')}
                {...register('storage.drivers.ftp.username')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.ftp?.username?.message as string)}
              />

              <Input
                label={t('form:input-label-ftp-password')}
                type="password"
                {...register('storage.drivers.ftp.password')}
                variant="outline"
                className="mb-5"
                error={t(errors?.storage?.drivers?.ftp?.password?.message as string)}
              />

              <Input
                label={t('form:input-label-ftp-port')}
                type="number"
                {...register('storage.drivers.ftp.port')}
                variant="outline"
                className="mb-5"
              />

              <Input
                label={t('form:input-label-ftp-base-url')}
                {...register('storage.drivers.ftp.base_url')}
                placeholder="https://files.example.com"
                variant="outline"
                className="mb-5"
              />

              <div className="mb-5">
                <SwitchInput
                  name="storage.drivers.ftp.ssl"
                  control={control}
                  label={t('form:input-label-ftp-ssl')}
                />
              </div>

              <div className="mb-5">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => testDriver('ftp')}
                  loading={testingDriver === 'ftp'}
                  disabled={testingDriver !== null}
                >
                  {t('form:button-test-connection')}
                </Button>
              </div>
            </>
          )}
        </Card>
      </div>

      {/* Test Results */}
      {testResult && (
        <div className="mb-5">
          <Alert
            message={testResult.message}
            variant={testResult.success ? 'success' : 'error'}
            closeable={true}
            onClose={() => setTestResult(null)}
          />
        </div>
      )}

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
