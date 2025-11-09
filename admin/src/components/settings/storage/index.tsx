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
import { useForm } from 'react-hook-form';
import { storageValidationSchema } from './storage-validation-schema';
import { SaveIcon } from '@/components/icons/save';
import { useConfirmRedirectIfDirty } from '@/utils/confirmed-redirect-if-dirty';
import Alert from '@/components/ui/alert';
import { useState, useMemo } from 'react';
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

  // Filter driver options to show only enabled drivers
  const driverOptions = useMemo(() => {
    // Local is always available
    const enabledDrivers = [allDriverOptions[0]];
    
    if (telegramEnabled) {
      enabledDrivers.push(allDriverOptions[1]);
    }
    if (googleDriveEnabled) {
      enabledDrivers.push(allDriverOptions[2]);
    }
    if (ftpEnabled) {
      enabledDrivers.push(allDriverOptions[3]);
    }
    
    return enabledDrivers;
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
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    const apiId = watch('storage.drivers.telegram.api_id');
    const apiHash = watch('storage.drivers.telegram.api_hash');
    const phone = watch('storage.drivers.telegram.phone');

    if (!apiId || !apiHash || !phone) {
      setTestResult({
        success: false,
        message: t('form:error-telegram-credentials-required'),
      });
      setTestingDriver(null);
      return;
    }

    try {
      const response = await apiClient.post('/api/storage/telegram/auth/start', {
        phone,
        api_id: apiId,
        api_hash: apiHash,
      });

      if (response.data.success) {
        setTelegramAuthStep('code');
        setTelegramPhone(phone);
        setTelegramAuthData(response.data);
        setTestResult({
          success: true,
          message: t('form:telegram-code-sent'),
        });
      } else {
        setTestResult({
          success: false,
          message: response.data.message,
        });
      }
    } catch (error: any) {
      setTestResult({
        success: false,
        message: error.response?.data?.message || t('form:error-telegram-auth-failed'),
      });
    } finally {
      setTestingDriver(null);
    }
  };

  const handleTelegramVerifyCode = async () => {
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    if (!telegramCode) {
      setTelegramAuthError(t('form:error-code-required'));
      setTestingDriver(null);
      return;
    }

    try {
      const response = await apiClient.post('/api/storage/telegram/auth/verify', {
        phone: telegramPhone,
        code: telegramCode,
      });

      if (response.data.success) {
        if (response.data.data?.requires_2fa) {
          setTelegramAuthStep('2fa');
          setTestResult({
            success: true,
            message: t('form:telegram-2fa-required'),
          });
        } else {
          setTelegramAuthStep('authenticated');
          setTestResult({
            success: true,
            message: t('form:telegram-authenticated-successfully'),
          });
        }
      } else {
        setTelegramAuthError(response.data.message);
      }
    } catch (error: any) {
      setTelegramAuthError(error.response?.data?.message || t('form:error-verification-failed'));
    } finally {
      setTestingDriver(null);
    }
  };

  const handleTelegramVerify2FA = async () => {
    setTestingDriver('telegram');
    setTelegramAuthError('');
    setTestResult(null);

    if (!telegram2FA) {
      setTelegramAuthError(t('form:error-2fa-required'));
      setTestingDriver(null);
      return;
    }

    try {
      const response = await apiClient.post('/api/storage/telegram/auth/2fa', {
        phone: telegramPhone,
        password: telegram2FA,
      });

      if (response.data.success) {
        setTelegramAuthStep('authenticated');
        setTestResult({
          success: true,
          message: t('form:telegram-authenticated-successfully'),
        });
      } else {
        setTelegramAuthError(response.data.message);
      }
    } catch (error: any) {
      setTelegramAuthError(error.response?.data?.message || t('form:error-2fa-verification-failed'));
    } finally {
      setTestingDriver(null);
    }
  };

  const handleTelegramTestChannel = async () => {
    setTestingDriver('telegram');
    setTestResult(null);

    const apiId = watch('storage.drivers.telegram.api_id');
    const apiHash = watch('storage.drivers.telegram.api_hash');
    const phone = watch('storage.drivers.telegram.phone');
    const channelId = watch('storage.drivers.telegram.channel_id');

    if (!channelId) {
      setTestResult({
        success: false,
        message: t('form:error-channel-id-required'),
      });
      setTestingDriver(null);
      return;
    }

    try {
      const response = await apiClient.post('/api/storage/telegram/test-channel', {
        api_id: apiId,
        api_hash: apiHash,
        phone,
        channel_id: channelId,
      });

      setTestResult(response.data);
    } catch (error: any) {
      setTestResult({
        success: false,
        message: error.response?.data?.message || t('form:error-channel-test-failed'),
      });
    } finally {
      setTestingDriver(null);
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
            <SelectInput
              name="storage.default_driver"
              control={control}
              options={driverOptions}
              getOptionLabel={(option: any) => option.label}
              getOptionValue={(option: any) => option.value}
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
            <SelectInput
              name="storage.type_mapping.image"
              control={control}
              options={driverOptions}
              getOptionLabel={(option: any) => option.label}
              getOptionValue={(option: any) => option.value}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-video-driver')}</Label>
            <SelectInput
              name="storage.type_mapping.video"
              control={control}
              options={driverOptions}
              getOptionLabel={(option: any) => option.label}
              getOptionValue={(option: any) => option.value}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-digital-file-driver')}</Label>
            <SelectInput
              name="storage.type_mapping.digital_file"
              control={control}
              options={driverOptions}
              getOptionLabel={(option: any) => option.label}
              getOptionValue={(option: any) => option.value}
            />
          </div>

          <div className="mb-5">
            <Label>{t('form:input-label-storage-document-driver')}</Label>
            <SelectInput
              name="storage.type_mapping.document"
              control={control}
              options={driverOptions}
              getOptionLabel={(option: any) => option.label}
              getOptionValue={(option: any) => option.value}
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
