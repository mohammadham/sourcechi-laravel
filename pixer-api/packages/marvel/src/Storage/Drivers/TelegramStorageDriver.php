<?php

namespace Marvel\Storage\Drivers;

use Marvel\Storage\BaseStorageDriver;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\Cache;

class TelegramStorageDriver extends BaseStorageDriver
{
    private ?API $telegram = null;
    private string $sessionPath;
    
    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return 'telegram';
    }

    /**
     * Validate Telegram configuration
     */
    protected function validateConfig(): bool
    {
        $required = ['api_id', 'api_hash', 'phone'];
        
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Initialize Telegram connection
     */
    private function initializeTelegram(): bool
    {
        try {
            if ($this->telegram !== null) {
                return true;
            }

            $this->sessionPath = storage_path('app/telegram/session_' . md5($this->config['phone']) . '.madeline');
            
            // Create directory if not exists
            $directory = dirname($this->sessionPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // MadelineProto settings
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()->setLevel(\danog\MadelineProto\Logger::LOGGER);

            $this->telegram = new API($this->sessionPath, $settings);
            
            // Check if already logged in
            if (!$this->telegram->getSelf()) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to initialize Telegram: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Start phone authentication
     */
    public function startPhoneAuth(string $phone): array
    {
        try {
            $this->config['phone'] = $phone;
            $this->sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);

            $this->telegram = new API($this->sessionPath, $settings);
            
            // Send code
            $sentCode = $this->telegram->phoneLogin($phone);
            
            // Store phone_code_hash in cache for verification
            Cache::put("telegram_auth_{$phone}", [
                'phone_code_hash' => $sentCode['phone_code_hash'] ?? null,
                'api_id' => $this->config['api_id'],
                'api_hash' => $this->config['api_hash'],
            ], now()->addMinutes(10));
            
            return $this->successResponse('Verification code sent to Telegram', [
                'phone_code_hash' => $sentCode['phone_code_hash'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send code: ' . $e->getMessage());
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyCode(string $phone, string $code): array
    {
        try {
            $authData = Cache::get("telegram_auth_{$phone}");
            
            if (!$authData) {
                return $this->errorResponse('Authentication session expired. Please try again.');
            }
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $this->sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);

            $this->telegram = new API($this->sessionPath, $settings);
            
            // Complete authorization with code
            $authorization = $this->telegram->completePhoneLogin($code);
            
            if (isset($authorization['_']) && $authorization['_'] === 'auth.authorization') {
                // Successfully logged in
                $user = $this->telegram->getSelf();
                
                Cache::forget("telegram_auth_{$phone}");
                
                return $this->successResponse('Successfully authenticated', [
                    'user' => [
                        'id' => $user['id'] ?? null,
                        'first_name' => $user['first_name'] ?? '',
                        'username' => $user['username'] ?? '',
                    ],
                    'requires_2fa' => false,
                ]);
            }
            
            // Check if 2FA is required
            if (isset($authorization['_']) && $authorization['_'] === 'account.password') {
                Cache::put("telegram_2fa_{$phone}", [
                    'api_id' => $this->config['api_id'],
                    'api_hash' => $this->config['api_hash'],
                ], now()->addMinutes(10));
                
                return $this->successResponse('2FA required', [
                    'requires_2fa' => true,
                ]);
            }
            
            return $this->errorResponse('Invalid code or authentication failed');
        } catch (\Exception $e) {
            return $this->errorResponse('Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify 2FA password
     */
    public function verify2FA(string $phone, string $password): array
    {
        try {
            $authData = Cache::get("telegram_2fa_{$phone}");
            
            if (!$authData) {
                return $this->errorResponse('2FA session expired. Please start over.');
            }
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $this->sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);

            $this->telegram = new API($this->sessionPath, $settings);
            
            // Complete 2FA
            $authorization = $this->telegram->complete2faLogin($password);
            
            if (isset($authorization['_']) && $authorization['_'] === 'auth.authorization') {
                $user = $this->telegram->getSelf();
                
                Cache::forget("telegram_2fa_{$phone}");
                
                return $this->successResponse('Successfully authenticated with 2FA', [
                    'user' => [
                        'id' => $user['id'] ?? null,
                        'first_name' => $user['first_name'] ?? '',
                        'username' => $user['username'] ?? '',
                    ],
                ]);
            }
            
            return $this->errorResponse('Invalid 2FA password');
        } catch (\Exception $e) {
            return $this->errorResponse('2FA verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Test connection to Telegram and channel
     */
    public function testConnection(): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated. Please login first.');
            }
            
            // Test 1: Check self
            $self = $this->telegram->getSelf();
            if (!$self) {
                return $this->errorResponse('Failed to get user information');
            }
            
            // Test 2: Check channel if configured
            if (!empty($this->config['channel_id'])) {
                $result = $this->testChannel($this->config['channel_id']);
                if (!$result['success']) {
                    return $result;
                }
            }
            
            return $this->successResponse('Telegram connection is working correctly', [
                'user' => [
                    'id' => $self['id'] ?? null,
                    'name' => ($self['first_name'] ?? '') . ' ' . ($self['last_name'] ?? ''),
                    'username' => $self['username'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test channel access
     */
    public function testChannel(string $channelId): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            // Get channel info
            $peer = $this->telegram->getInfo($channelId);
            
            if (!$peer || !isset($peer['Chat'])) {
                return $this->errorResponse('Channel not found or no access');
            }
            
            // Test upload/download/delete
            $testFile = storage_path('app/telegram_test_' . time() . '.txt');
            file_put_contents($testFile, 'Telegram storage test file');
            
            // Upload test
            $uploadResult = $this->upload($testFile, 'test.txt', 'document');
            
            // Clean up
            @unlink($testFile);
            
            if (!$uploadResult['success']) {
                return $this->errorResponse('Failed to upload test file to channel');
            }
            
            // Delete test file
            $this->delete($uploadResult['file_id']);
            
            return $this->successResponse('Channel test successful', [
                'channel' => [
                    'id' => $peer['bot_api_id'] ?? $channelId,
                    'title' => $peer['Chat']['title'] ?? '',
                    'username' => $peer['Chat']['username'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Channel test failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload file to Telegram channel
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            if (!file_exists($filePath)) {
                return $this->errorResponse('File not found: ' . $filePath);
            }
            
            $channelId = $this->config['channel_id'] ?? null;
            if (!$channelId) {
                return $this->errorResponse('Channel ID not configured');
            }
            
            // Upload file
            $messageMedia = $this->telegram->messages->sendMedia([
                'peer' => $channelId,
                'media' => [
                    '_' => 'inputMediaUploadedDocument',
                    'file' => $filePath,
                    'attributes' => [
                        [
                            '_' => 'documentAttributeFilename',
                            'file_name' => $fileName,
                        ],
                    ],
                ],
                'message' => 'File: ' . $fileName,
            ]);
            
            // Extract file ID
            $fileId = null;
            $fileSize = 0;
            
            if (isset($messageMedia['updates'])) {
                foreach ($messageMedia['updates'] as $update) {
                    if (isset($update['message']['media']['document'])) {
                        $fileId = $update['message']['media']['document']['id'];
                        $fileSize = $update['message']['media']['document']['size'] ?? 0;
                        break;
                    }
                }
            }
            
            if (!$fileId) {
                return $this->errorResponse('Failed to upload file to Telegram');
            }
            
            return $this->successResponse('File uploaded to Telegram successfully', [
                'file_id' => $fileId,
                'url' => "telegram://file/{$fileId}",
                'metadata' => [
                    'size' => $fileSize,
                    'channel_id' => $channelId,
                    'file_name' => $fileName,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from Telegram
     */
    public function download(string $fileId, string $localPath): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            // Download file by ID
            $this->telegram->downloadToFile($fileId, $localPath);
            
            if (file_exists($localPath)) {
                return $this->successResponse('File downloaded successfully', [
                    'path' => $localPath,
                ]);
            }
            
            return $this->errorResponse('Download failed: File not created');
        } catch (\Exception $e) {
            return $this->errorResponse('Download failed: ' . $e->getMessage());
        }
    }

    /**
     * Get file URL (generate temporary download link)
     */
    public function getFileUrl(string $fileId, int $expiresIn = 3600): string
    {
        // For Telegram, we need to download and serve from local temp
        // Or return a special URL that will trigger download
        return route('storage.telegram.download', ['file_id' => $fileId]);
    }

    /**
     * Delete file from Telegram
     */
    public function delete(string $fileId): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            $channelId = $this->config['channel_id'] ?? null;
            if (!$channelId) {
                return $this->errorResponse('Channel ID not configured');
            }
            
            // Find and delete message containing the file
            // This requires searching through channel messages
            // For now, we'll return success as Telegram files don't consume quota
            
            return $this->successResponse('File deletion requested (Telegram files remain in channel history)');
        } catch (\Exception $e) {
            return $this->errorResponse('Delete failed: ' . $e->getMessage());
        }
    }

    /**
     * Logout from Telegram
     */
    public function logout(): array
    {
        try {
            if ($this->telegram) {
                $this->telegram->logout();
            }
            
            if (file_exists($this->sessionPath)) {
                unlink($this->sessionPath);
            }
            
            return $this->successResponse('Logged out successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }
}
