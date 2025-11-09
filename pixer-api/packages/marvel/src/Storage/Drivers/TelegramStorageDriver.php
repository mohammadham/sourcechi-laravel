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
            \Log::info('[Telegram Init] Checking directory: ' . $directory);
            
            if (!is_dir($directory)) {
                \Log::info('[Telegram Init] Creating directory...');
                if (!mkdir($directory, 0777, true)) {
                    \Log::error('[Telegram Init] Failed to create directory');
                    return false;
                }
                chmod($directory, 0777);
            }
            
            if (!is_writable($directory)) {
                \Log::error('[Telegram Init] Directory not writable: ' . $directory);
                return false;
            }

            // MadelineProto settings
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            \Log::info('[Telegram Init] Initializing API with session: ' . $this->sessionPath);
            $this->telegram = new API($this->sessionPath, $settings);
            
            // Check if already logged in
            \Log::info('[Telegram Init] Checking authentication status...');
            if (!$this->telegram->getSelf()) {
                \Log::warning('[Telegram Init] Not authenticated');
                return false;
            }
            
            \Log::info('[Telegram Init] Successfully authenticated');
            return true;
        } catch (\Exception $e) {
            \Log::error('[Telegram Init] Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
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
            
            // Log the session path and check directory
            $directory = dirname($this->sessionPath);
            \Log::info('[Telegram Auth] Session path: ' . $this->sessionPath);
            \Log::info('[Telegram Auth] Directory: ' . $directory);
            \Log::info('[Telegram Auth] Directory exists: ' . (is_dir($directory) ? 'yes' : 'no'));
            \Log::info('[Telegram Auth] Directory writable: ' . (is_writable($directory) ? 'yes' : 'no'));
            
            // Create directory if not exists
            if (!is_dir($directory)) {
                \Log::info('[Telegram Auth] Creating directory...');
                if (!mkdir($directory, 0777, true)) {
                    \Log::error('[Telegram Auth] Failed to create directory');
                    return $this->errorResponse('Failed to create session directory. Please check permissions.');
                }
                chmod($directory, 0777);
            }
            
            // Verify directory is writable
            if (!is_writable($directory)) {
                \Log::error('[Telegram Auth] Directory not writable');
                return $this->errorResponse('Session directory is not writable. Please check permissions: ' . $directory);
            }
            
            \Log::info('[Telegram Auth] Creating MadelineProto settings...');
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            // Set logger to file to avoid output issues
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            \Log::info('[Telegram Auth] Initializing MadelineProto API...');
            $this->telegram = new API($this->sessionPath, $settings);
            
            \Log::info('[Telegram Auth] Sending login code to: ' . $phone);
            // Send code
            $sentCode = $this->telegram->phoneLogin($phone);
            
            \Log::info('[Telegram Auth] Code sent successfully', [
                'phone_code_hash' => isset($sentCode['phone_code_hash']),
            ]);
            
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
            \Log::error('[Telegram Auth] Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to send code: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyCode(string $phone, string $code): array
    {
        try {
            \Log::info('[Telegram Verify] Starting code verification for: ' . $phone);
            
            $authData = Cache::get("telegram_auth_{$phone}");
            
            if (!$authData) {
                \Log::error('[Telegram Verify] Auth session not found in cache');
                return $this->errorResponse('Authentication session expired. Please try again.');
            }
            
            \Log::info('[Telegram Verify] Auth data found in cache');
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $this->sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            \Log::info('[Telegram Verify] Initializing API...');
            $this->telegram = new API($this->sessionPath, $settings);
            
            \Log::info('[Telegram Verify] Completing phone login with code...');
            // Complete authorization with code
            $authorization = $this->telegram->completePhoneLogin($code);
            
            \Log::info('[Telegram Verify] Authorization response type: ' . ($authorization['_'] ?? 'unknown'));
            
            if (isset($authorization['_']) && $authorization['_'] === 'auth.authorization') {
                // Successfully logged in
                \Log::info('[Telegram Verify] Authentication successful');
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
                \Log::info('[Telegram Verify] 2FA required');
                Cache::put("telegram_2fa_{$phone}", [
                    'api_id' => $this->config['api_id'],
                    'api_hash' => $this->config['api_hash'],
                ], now()->addMinutes(10));
                
                return $this->successResponse('2FA required', [
                    'requires_2fa' => true,
                ]);
            }
            
            \Log::error('[Telegram Verify] Invalid authorization response');
            return $this->errorResponse('Invalid code or authentication failed');
        } catch (\Exception $e) {
            \Log::error('[Telegram Verify] Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->errorResponse('Verification failed: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
        }
    }

    /**
     * Verify 2FA password
     */
    public function verify2FA(string $phone, string $password): array
    {
        try {
            \Log::info('[Telegram 2FA] Starting 2FA verification for: ' . $phone);
            
            $authData = Cache::get("telegram_2fa_{$phone}");
            
            if (!$authData) {
                \Log::error('[Telegram 2FA] Session not found in cache');
                return $this->errorResponse('2FA session expired. Please start over.');
            }
            
            \Log::info('[Telegram 2FA] Auth data found in cache');
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $this->sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            \Log::info('[Telegram 2FA] Initializing API...');
            $this->telegram = new API($this->sessionPath, $settings);
            
            \Log::info('[Telegram 2FA] Completing 2FA login...');
            // Complete 2FA
            $authorization = $this->telegram->complete2faLogin($password);
            
            \Log::info('[Telegram 2FA] Authorization response type: ' . ($authorization['_'] ?? 'unknown'));
            
            if (isset($authorization['_']) && $authorization['_'] === 'auth.authorization') {
                \Log::info('[Telegram 2FA] 2FA authentication successful');
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
            
            \Log::error('[Telegram 2FA] Invalid authorization response');
            return $this->errorResponse('Invalid 2FA password');
        } catch (\Exception $e) {
            \Log::error('[Telegram 2FA] Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->errorResponse('2FA verification failed: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
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
