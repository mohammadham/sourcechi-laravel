<?php

namespace Marvel\Storage\Drivers;

use Marvel\Storage\BaseStorageDriver;
use Marvel\Storage\TelegramSessionManager;
use Marvel\Database\Models\TelegramSession;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TelegramStorageDriver با پشتیبانی از Multi-Session و Load Balancing
 * 
 * نسخه 2.0 - با قابلیت مدیریت چندین سشن تلگرام
 */
class TelegramStorageDriver extends BaseStorageDriver
{
    private TelegramSessionManager $sessionManager;
    private ?TelegramSession $currentSession = null;
    private ?API $telegram = null;
    
    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        // parent::__construct($config);
        $this->config = $config;
        $this->sessionManager = new TelegramSessionManager();
    }
    
    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return 'telegram';
    }

    /**
     * Validate Telegram configuration
     * (برای سازگاری با کدهای قدیمی - در multi-session استفاده نمی‌شود)
     */
    protected function validateConfig(): bool
    {
        // در multi-session mode، validation توسط TelegramSession model انجام می‌شود
        return true;
    }

    /**
     * Initialize کردن یک سشن خاص
     * 
     * @param TelegramSession $session
     * @return API|null
     */
    private function initializeSession(TelegramSession $session): ?API
    {
        try {
            Log::info('[TelegramDriver] Initializing session', [
                'session_id' => $session->id,
                'session_name' => $session->name,
            ]);
            
            $sessionPath = $session->getSessionPath();
            
            // Create directory if not exists
            $directory = dirname($sessionPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    Log::error('[TelegramDriver] Failed to create directory', [
                        'directory' => $directory,
                    ]);
                    return null;
                }
                chmod($directory, 0777);
            }
            
            if (!is_writable($directory)) {
                Log::error('[TelegramDriver] Directory not writable', [
                    'directory' => $directory,
                ]);
                return null;
            }

            // MadelineProto settings
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $session->api_id)
                ->setApiHash($session->api_hash);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            $telegram = new API($sessionPath, $settings);
            
            // Check if already logged in
            if (!$telegram->getSelf()) {
                Log::warning('[TelegramDriver] Session not authenticated', [
                    'session_id' => $session->id,
                ]);
                return null;
            }
            
            Log::info('[TelegramDriver] Session initialized successfully', [
                'session_id' => $session->id,
                'session_name' => $session->name,
            ]);
            
            return $telegram;
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Failed to initialize session', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Initialize Telegram connection (متد قدیمی - برای سازگاری با احراز هویت)
     * استفاده در: startPhoneAuth, verifyCode, verify2FA, testConnection
     */
    private function initializeTelegram(): bool
    {
        try {
            if ($this->telegram !== null) {
                return true;
            }

            // برای compatibility با کدهای قدیمی که از config استفاده می‌کنند
            if (empty($this->config['phone'])) {
                Log::error('[TelegramDriver] Phone not provided in config');
                return false;
            }

            $sessionPath = storage_path('app/telegram/session_' . md5($this->config['phone']) . '.madeline');
            
            // Create directory if not exists
            $directory = dirname($sessionPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    Log::error('[TelegramDriver] Failed to create directory');
                    return false;
                }
                chmod($directory, 0777);
            }
            
            if (!is_writable($directory)) {
                Log::error('[TelegramDriver] Directory not writable');
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

            $this->telegram = new API($sessionPath, $settings);
            
            // Check if already logged in
            if (!$this->telegram->getSelf()) {
                Log::warning('[TelegramDriver] Not authenticated');
                return false;
            }
            
            Log::info('[TelegramDriver] Successfully authenticated');
            return true;
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if already authenticated (متد قدیمی - نگه‌داری برای سازگاری)
     */
    public function checkAuthStatus(): array
    {
        // این متد برای authentication استفاده می‌شود، نه برای multi-session
        // پس کد قبلی را نگه می‌داریم
        
        try {
            if (!isset($this->config['phone']) || empty($this->config['phone'])) {
                return $this->successResponse('Not authenticated', [
                    'authenticated' => false,
                ]);
            }
            
            $sessionPath = storage_path('app/telegram/session_' . md5($this->config['phone']) . '.madeline');
            
            if (!file_exists($sessionPath)) {
                return $this->successResponse('Not authenticated', [
                    'authenticated' => false,
                ]);
            }
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);
            
            $this->telegram = new API($sessionPath, $settings);
            $self = $this->telegram->getSelf();
            
            if ($self) {
                return $this->successResponse('Authenticated', [
                    'authenticated' => true,
                    'user' => [
                        'id' => $self['id'] ?? null,
                        'first_name' => $self['first_name'] ?? '',
                        'username' => $self['username'] ?? '',
                        'phone' => $self['phone'] ?? '',
                    ],
                ]);
            }
            
            return $this->successResponse('Not authenticated', [
                'authenticated' => false,
            ]);
        } catch (\Exception $e) {
            return $this->successResponse('Not authenticated', [
                'authenticated' => false,
            ]);
        }
    }
    
    /**
     * متدهای احراز هویت (نگه‌داری برای سازگاری)
     * این متدها برای افزودن سشن جدید استفاده می‌شوند
     */
    
    public function startPhoneAuth(string $phone, int $retryCount = 0): array
    {
        // کد قبلی را نگه می‌داریم
        try {
            $this->config['phone'] = $phone;
            $sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $directory = dirname($sessionPath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    return $this->errorResponse('Failed to create session directory');
                }
                chmod($directory, 0777);
            }
            
            if (!is_writable($directory)) {
                return $this->errorResponse('Session directory is not writable');
            }
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            $this->telegram = new API($sessionPath, $settings);
            
            // Check if already logged in
            try {
                $self = $this->telegram->getSelf();
                if ($self) {
                    return $this->successResponse('Already authenticated', [
                        'authenticated' => true,
                        'user' => [
                            'id' => $self['id'] ?? null,
                            'first_name' => $self['first_name'] ?? '',
                            'username' => $self['username'] ?? '',
                        ],
                    ]);
                }
            } catch (\Exception $e) {
                // Not logged in, continue
            }
            
            // Send code
            $sentCode = $this->telegram->phoneLogin($phone);
            
            // Store in cache
            Cache::put("telegram_auth_{$phone}", [
                'phone_code_hash' => $sentCode['phone_code_hash'] ?? null,
                'api_id' => $this->config['api_id'],
                'api_hash' => $this->config['api_hash'],
            ], now()->addMinutes(10));
            
            return $this->successResponse('Verification code sent to Telegram', [
                'phone_code_hash' => $sentCode['phone_code_hash'] ?? null,
            ]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Handle AUTH_RESTART
            if (strpos($errorMessage, 'AUTH_RESTART') !== false && $retryCount < 2) {
                $this->deleteSessionFiles($sessionPath ?? '');
                sleep(1);
                return $this->startPhoneAuth($phone, $retryCount + 1);
            }
            
            return $this->errorResponse('Failed to send code: ' . $errorMessage);
        }
    }
    
    private function deleteSessionFiles(string $sessionPath): void
    {
        try {
            if (file_exists($sessionPath)) {
                unlink($sessionPath);
            }
            
            $lockFile = $sessionPath . '.lock';
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
            
            $tempFile = $sessionPath . '.temp.session';
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Failed to delete session files: ' . $e->getMessage());
        }
    }

    public function verifyCode(string $phone, string $code): array
    {
        try {
            $authData = Cache::get("telegram_auth_{$phone}");
            
            if (!$authData) {
                return $this->errorResponse('Authentication session expired');
            }
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            $this->telegram = new API($sessionPath, $settings);
            
            $authorization = $this->telegram->completePhoneLogin($code);
            
            if (isset($authorization['_']) && $authorization['_'] === 'auth.authorization') {
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

    public function verify2FA(string $phone, string $password): array
    {
        try {
            $authData = Cache::get("telegram_2fa_{$phone}");
            
            if (!$authData) {
                return $this->errorResponse('2FA session expired');
            }
            
            $this->config['phone'] = $phone;
            $this->config['api_id'] = $authData['api_id'];
            $this->config['api_hash'] = $authData['api_hash'];
            $sessionPath = storage_path('app/telegram/session_' . md5($phone) . '.madeline');
            
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) $this->config['api_id'])
                ->setApiHash($this->config['api_hash']);
            
            $settings->getLogger()
                ->setType(\danog\MadelineProto\Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram.log'))
                ->setLevel(\danog\MadelineProto\Logger::NOTICE);

            $this->telegram = new API($sessionPath, $settings);
            
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
     * Test connection (متد قدیمی - برای سازگاری)
     */
    public function testConnection(): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            $self = $this->telegram->getSelf();
            if (!$self) {
                return $this->errorResponse('Failed to get user information');
            }
            
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
     * Test channel access (متد قدیمی - برای سازگاری)
     */
    public function testChannel(string $channelId): array
    {
        try {
            if (!$this->initializeTelegram()) {
                return $this->errorResponse('Not authenticated');
            }
            
            $peer = $this->telegram->getInfo($channelId);
            
            if (!$peer || !isset($peer['Chat'])) {
                return $this->errorResponse('Channel not found or no access');
            }
            
            $testFile = storage_path('app/telegram_test_' . time() . '.txt');
            file_put_contents($testFile, 'Telegram storage test file');
            
            $uploadResult = $this->upload($testFile, 'test.txt', 'document');
            
            @unlink($testFile);
            
            if (!$uploadResult['success']) {
                return $this->errorResponse('Failed to upload test file to channel');
            }
            
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
     * Upload file using multi-session
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        try {
            // انتخاب بهترین سشن
            $session = $this->sessionManager->selectBestSession();
            
            if (!$session) {
                Log::error('[TelegramDriver] No healthy session available for upload');
                return $this->errorResponse('No healthy session available');
            }
            
            Log::info('[TelegramDriver] Starting upload with session', [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'file' => $fileName,
            ]);
            
            if (!file_exists($filePath)) {
                return $this->errorResponse('File not found: ' . $filePath);
            }
            
            // Initialize سشن
            $telegram = $this->initializeSession($session);
            if (!$telegram) {
                return $this->errorResponse('Failed to initialize session');
            }
            
            $mimeType = mime_content_type($filePath);
            $fileSize = filesize($filePath);
            
            // Mark session as busy
            $session->incrementActiveDownloads();  // استفاده برای آپلود هم
            
            try {
                // Upload file
                $messageMedia = $telegram->messages->sendMedia([
                    'peer' => $session->channel_id,
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
                    'message' => 'File: ' . $fileName . ' | Size: ' . $this->formatBytes($fileSize),
                ]);
                
                // Extract info
                $messageId = null;
                $documentId = null;
                $uploadedSize = 0;
                
                if (isset($messageMedia['updates'])) {
                    foreach ($messageMedia['updates'] as $update) {
                        if (isset($update['message'])) {
                            $messageId = $update['message']['id'];
                            
                            if (isset($update['message']['media']['document'])) {
                                $document = $update['message']['media']['document'];
                                $documentId = $document['id'];
                                $uploadedSize = $document['size'] ?? $fileSize;
                            }
                            break;
                        }
                    }
                }
                
                if (!$messageId) {
                    Log::error('[TelegramDriver] Failed to extract message ID');
                    return $this->errorResponse('Upload failed: No message ID received');
                }
                
                // Update stats
                $session->incrementTotalUploads();
                
                Log::info('[TelegramDriver] Upload successful', [
                    'session_id' => $session->id,
                    'message_id' => $messageId,
                    'document_id' => $documentId,
                    'file_size' => $uploadedSize,
                ]);
                
                return $this->successResponse('File uploaded to Telegram successfully', [
                    'file_id' => $documentId,
                    'url' => "telegram://file/{$documentId}",
                    'metadata' => [
                        'telegram_message_id' => $messageId,
                        'telegram_document_id' => $documentId,
                        'telegram_channel_id' => $session->channel_id,
                        'telegram_file_size' => $uploadedSize,
                        'telegram_mime_type' => $mimeType,
                        'original_name' => $fileName,
                        'uploaded_at' => now()->toDateTimeString(),
                        'session_id' => $session->id,
                        'session_name' => $session->name,
                    ],
                ]);
            } finally {
                // Release session
                $session->decrementActiveDownloads();
            }
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Upload exception', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Download (deprecated)
     */
    public function download(string $fileId, string $localPath): array
    {
        return $this->errorResponse('Method deprecated. Use downloadByMessageId() instead.');
    }
    
    /**
     * Download by message ID using multi-session
     */
    public function downloadByMessageId(int $messageId, string $channelId, string $localPath): array
    {
        try {
            // انتخاب بهترین سشن
            $session = $this->sessionManager->selectBestSession();
            
            if (!$session) {
                Log::error('[TelegramDriver] No healthy session available for download');
                return $this->errorResponse('No healthy session available');
            }
            
            Log::info('[TelegramDriver] Starting download with session', [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'message_id' => $messageId,
            ]);
            
            // Initialize session
            $telegram = $this->initializeSession($session);
            if (!$telegram) {
                return $this->errorResponse('Failed to initialize session');
            }
            
            // Mark session as busy
            $session->incrementActiveDownloads();
            
            try {
                // Get message
                $messages = $telegram->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [$messageId],
                ]);
                
                if (empty($messages['messages'])) {
                    Log::error('[TelegramDriver] Message not found', ['message_id' => $messageId]);
                    return $this->errorResponse('Message not found');
                }
                
                $message = $messages['messages'][0];
                $document = $message['media']['document'] ?? null;
                
                if (!$document) {
                    Log::error('[TelegramDriver] No document in message', ['message_id' => $messageId]);
                    return $this->errorResponse('Document not found in message');
                }
                
                // Download
                $telegram->downloadToFile($document, $localPath);
                
                if (file_exists($localPath)) {
                    $size = filesize($localPath);
                    
                    // Update stats
                    $session->incrementTotalDownloads();
                    
                    Log::info('[TelegramDriver] Download successful', [
                        'session_id' => $session->id,
                        'path' => $localPath,
                        'size' => $size,
                    ]);
                    
                    return $this->successResponse('File downloaded successfully', [
                        'path' => $localPath,
                        'size' => $size,
                    ]);
                }
                
                return $this->errorResponse('Download failed: File not created');
            } finally {
                // Release session
                $session->decrementActiveDownloads();
            }
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Download exception', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Download failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Stream to output using multi-session
     */
    public function streamToOutput(int $messageId, string $channelId): bool
    {
        try {
            // انتخاب بهترین سشن
            $session = $this->sessionManager->selectBestSession();
            
            if (!$session) {
                Log::error('[TelegramDriver] No healthy session available for streaming');
                return false;
            }
            
            Log::info('[TelegramDriver] Starting stream with session', [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'message_id' => $messageId,
            ]);
            
            // Initialize session
            $telegram = $this->initializeSession($session);
            if (!$telegram) {
                return false;
            }
            
            // Mark session as busy
            $session->incrementActiveDownloads();
            
            try {
                $messages = $telegram->channels->getMessages([
                    'channel' => $channelId,
                    'id' => [$messageId],
                ]);
                
                $document = $messages['messages'][0]['media']['document'] ?? null;
                
                if (!$document) {
                    return false;
                }
                
                // Stream
                $telegram->downloadToStream($document, 'php://output');
                
                // Update stats
                $session->incrementTotalDownloads();
                
                return true;
            } finally {
                // Release session
                $session->decrementActiveDownloads();
            }
        } catch (\Exception $e) {
            Log::error('[TelegramDriver] Stream exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $fileId, int $expiresIn = 3600): string
    {
        return route('storage.telegram.download', ['file_id' => $fileId]);
    }

    /**
     * Delete file
     */
    public function delete(string $fileId): array
    {
        // Telegram files remain in channel history
        return $this->successResponse('File deletion requested (Telegram files remain in channel history)');
    }

    /**
     * Logout
     */
    public function logout(): array
    {
        try {
            if ($this->telegram) {
                $this->telegram->logout();
            }
            
            if (isset($this->config['phone'])) {
                $sessionPath = storage_path('app/telegram/session_' . md5($this->config['phone']) . '.madeline');
                if (file_exists($sessionPath)) {
                    unlink($sessionPath);
                }
            }
            
            return $this->successResponse('Logged out successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }
}
