import { useState } from 'react';
import { useTranslation } from 'next-i18next';
import Modal from '@/components/ui/modal/modal';
import Button from '@/components/ui/button';
import Input from '@/components/ui/input';
import {
  useStartLoginMutation,
  useVerifyCodeMutation,
  useVerify2FAMutation,
} from '@/data/telegram-session';
import { toast } from 'react-toastify';

interface SessionLoginModalProps {
  open: boolean;
  onClose: () => void;
  sessionId: number;
  onSuccess?: () => void;
}

enum LoginStep {
  START = 'start',
  VERIFY_CODE = 'verify_code',
  VERIFY_2FA = 'verify_2fa',
}

export default function SessionLoginModal({
  open,
  onClose,
  sessionId,
  onSuccess,
}: SessionLoginModalProps) {
  const { t } = useTranslation('common');
  const [step, setStep] = useState<LoginStep>(LoginStep.START);
  const [phoneCodeHash, setPhoneCodeHash] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');

  const startLoginMutation = useStartLoginMutation();
  const verifyCodeMutation = useVerifyCodeMutation();
  const verify2FAMutation = useVerify2FAMutation();

  const handleStartLogin = async () => {
    try {
      const response = await startLoginMutation.mutateAsync(sessionId);
      if (response.phone_code_hash) {
        setPhoneCodeHash(response.phone_code_hash);
        setStep(LoginStep.VERIFY_CODE);
      }
    } catch (error: any) {
      toast.error(error?.message || t('telegram-login-start-error'));
    }
  };

  const handleVerifyCode = async () => {
    try {
      const response = await verifyCodeMutation.mutateAsync({
        id: sessionId,
        input: { code, phone_code_hash: phoneCodeHash },
      });
      
      // Check if 2FA is required
      const res = response as any;
      if (res?.requires_2fa) {
        setStep(LoginStep.VERIFY_2FA);
      } else {
        onSuccess?.();
        handleClose();
      }
    } catch (error: any) {
      if (error?.message?.includes('2FA') || error?.message?.includes('password')) {
        setStep(LoginStep.VERIFY_2FA);
      } else {
        toast.error(error?.message || t('telegram-login-verify-error'));
      }
    }
  };

  const handleVerify2FA = async () => {
    try {
      await verify2FAMutation.mutateAsync({
        id: sessionId,
        input: { password },
      });
      onSuccess?.();
      handleClose();
    } catch (error: any) {
      toast.error(error?.message || t('telegram-login-2fa-error'));
    }
  };

  const handleClose = () => {
    setStep(LoginStep.START);
    setPhoneCodeHash('');
    setCode('');
    setPassword('');
    onClose();
  };

  const isLoading =
    startLoginMutation.isLoading ||
    verifyCodeMutation.isLoading ||
    verify2FAMutation.isLoading;

  return (
    <Modal open={open} onClose={handleClose}>
      <div className="m-auto w-full max-w-md rounded-md bg-light p-6 sm:w-[24rem]">
        <h3 className="mb-6 text-center text-lg font-semibold">
          {t('telegram-session-login')}
        </h3>

        {step === LoginStep.START && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              {t('telegram-login-start-description')}
            </p>
            <Button
              onClick={handleStartLogin}
              loading={isLoading}
              className="w-full"
            >
              {t('telegram-send-code')}
            </Button>
          </div>
        )}

        {step === LoginStep.VERIFY_CODE && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              {t('telegram-login-code-description')}
            </p>
            <Input
              name="code"
              label={t('telegram-login-code')}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              placeholder="12345"
              required
            />
            <Button
              onClick={handleVerifyCode}
              loading={isLoading}
              className="w-full"
              disabled={!code}
            >
              {t('telegram-verify-code')}
            </Button>
          </div>
        )}

        {step === LoginStep.VERIFY_2FA && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              {t('telegram-login-2fa-description')}
            </p>
            <Input
              name="password"
              label={t('telegram-login-2fa')}
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="********"
              required
            />
            <Button
              onClick={handleVerify2FA}
              loading={isLoading}
              className="w-full"
              disabled={!password}
            >
              {t('telegram-verify-2fa')}
            </Button>
          </div>
        )}

        <Button
          variant="outline"
          onClick={handleClose}
          className="mt-4 w-full"
          disabled={isLoading}
        >
          {t('text-cancel')}
        </Button>
      </div>
    </Modal>
  );
}
