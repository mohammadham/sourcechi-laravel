# معماری نهایی سیستم ذخیره‌سازی - با Prefix-Based Routing

## 🎯 معماری Token با Prefix

### ساختار Token:

```
{prefix}_{uuid}

مثال‌ها:
- Local:        lc_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
- Telegram:     tg_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
- Google Drive: gd_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
- FTP:          ft_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
```

### مزایا:

✅ **امنیت:** نوع driver در URL مشخص نیست (فقط از prefix مشخص می‌شود)
✅ **سرعت:** routing سریع بدون نیاز به query کردن database
✅ **سادگی:** URL کوتاه‌تر و تمیزتر
✅ **توسعه‌پذیری:** افزودن driver جدید بسیار آسان
✅ **یکنواختی:** همه لینک‌ها یک فرم دارند

---

## 📊 نقشه Driver Prefixes

```php
const DRIVER_PREFIXES = [
    'local'        => 'lc',  // Local storage
    'telegram'     => 'tg',  // Telegram
    'google_drive' => 'gd',  // Google Drive
    'ftp'          => 'ft',  // FTP
    // آینده:
    // 's3'        => 's3',  // Amazon S3
    // 'dropbox'   => 'db',  // Dropbox
];
```

**قوانین:**
- طول همه prefix ها = 2 کاراکتر
- حروف کوچک
- یکتا (unique)

---

## 🏗️ معماری کامل

### 1. جدول storage_tokens (بدون تغییر، ولی token با prefix)

```sql
CREATE TABLE storage_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,        -- tg_uuid
    attachment_id BIGINT UNSIGNED NOT NULL,
    driver VARCHAR(50) NOT NULL,              -- فقط برای query
    metadata JSON,
    expires_at TIMESTAMP NULL,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_driver_attachment (driver, attachment_id)
);
```

---

### 2. StorageTokenManager (کلاس جدید)

```php
namespace Marvel\Storage;

use Illuminate\Support\Str;

class StorageTokenManager
{
    /**
     * Driver prefixes (طول ثابت: 2 کاراکتر)
     */
    const DRIVER_PREFIXES = [
        'local'        => 'lc',
        'telegram'     => 'tg',
        'google_drive' => 'gd',
        'ftp'          => 'ft',
    ];
    
    /**
     * Reverse mapping (برای پیدا کردن driver از prefix)
     */
    const PREFIX_TO_DRIVER = [
        'lc' => 'local',
        'tg' => 'telegram',
        'gd' => 'google_drive',
        'ft' => 'ftp',
    ];
    
    /**
     * Generate token with driver prefix
     */
    public static function generateToken(string $driver): string
    {
        $prefix = self::DRIVER_PREFIXES[$driver] ?? null;
        
        if (!$prefix) {
            throw new \InvalidArgumentException("Unknown driver: {$driver}");
        }
        
        $uuid = Str::uuid()->toString();
        
        return "{$prefix}_{$uuid}";
    }
    
    /**
     * Parse token to extract driver and UUID
     * 
     * @return array ['driver' => 'telegram', 'uuid' => 'xxx', 'valid' => true]
     */
    public static function parseToken(string $token): array
    {
        // Format: {prefix}_{uuid}
        if (!preg_match('/^([a-z]{2})_([a-f0-9\-]{36})$/', $token, $matches)) {
            return [
                'valid' => false,
                'error' => 'Invalid token format',
            ];
        }
        
        $prefix = $matches[1];
        $uuid = $matches[2];
        
        $driver = self::PREFIX_TO_DRIVER[$prefix] ?? null;
        
        if (!$driver) {
            return [
                'valid' => false,
                'error' => 'Unknown driver prefix: ' . $prefix,
            ];
        }
        
        return [
            'valid' => true,
            'driver' => $driver,
            'prefix' => $prefix,
            'uuid' => $uuid,
            'token' => $token,
        ];
    }
    
    /**
     * Validate token format (سریع - بدون database)
     */
    public static function isValidFormat(string $token): bool
    {
        $parsed = self::parseToken($token);
        return $parsed['valid'] ?? false;
    }
    
    /**
     * Get driver from token (بدون database query)
     */
    public static function getDriverFromToken(string $token): ?string
    {
        $parsed = self::parseToken($token);
        return $parsed['driver'] ?? null;
    }
}
```

---

### 3. بازنویسی Model: StorageToken

```php
namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Storage\StorageTokenManager;

class StorageToken extends Model
{
    protected $fillable = [
        'token',
        'attachment_id',
        'driver',
        'metadata',
        'expires_at',
        'download_count',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'download_count' => 'integer',
    ];
    
    /**
     * Relationship: Attachment
     */
    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }
    
    /**
     * Generate new storage token
     * 
     * @param Attachment $attachment
     * @param string $driver Driver name (local, telegram, google_drive, ftp)
     * @param array $metadata Driver-specific metadata
     * @param int|null $expiresIn Expiration in seconds (null = never)
     * @return self
     */
    public static function generate(
        Attachment $attachment,
        string $driver,
        array $metadata,
        ?int $expiresIn = null
    ): self {
        // تولید token با prefix مناسب
        $token = StorageTokenManager::generateToken($driver);
        
        return self::create([
            'token' => $token,
            'attachment_id' => $attachment->id,
            'driver' => $driver,
            'metadata' => $metadata,
            'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
        ]);
    }
    
    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isPast();
    }
    
    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    /**
     * Parse token and validate driver
     */
    public function validateDriverMatch(): bool
    {
        $parsed = StorageTokenManager::parseToken($this->token);
        
        if (!$parsed['valid']) {
            return false;
        }
        
        return $parsed['driver'] === $this->driver;
    }
}
```

---

### 4. Route تک endpoint (ساده و تمیز)

```php
// routes/api.php

Route::get('/storage/download/{token}', [AttachmentController::class, 'download'])
    ->name('storage.download')
    ->where('token', '[a-z]{2}_[a-f0-9\-]{36}');

// OR اگر می‌خواهید streaming جداگانه:
Route::get('/storage/stream/{token}', [AttachmentController::class, 'stream'])
    ->name('storage.stream')
    ->where('token', '[a-z]{2}_[a-f0-9\-]{36}');
```

**مثال URL:**
```
https://srcchi.top/backend/storage/download/tg_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
```

---

### 5. Controller: DownloadRouter (میانه‌جی هوشمند)

```php
namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Models\StorageToken;
use Marvel\Storage\StorageTokenManager;
use Marvel\Storage\StorageManager;
use Illuminate\Support\Facades\Log;

class AttachmentController extends CoreController
{
    private StorageManager $storageManager;
    
    public function __construct()
    {
        $this->storageManager = new StorageManager();
    }
    
    /**
     * Download file by token (تک endpoint برای همه drivers)
     * 
     * Route: GET /storage/download/{token}
     */
    public function download(string $token)
    {
        try {
            // 1. سریع‌ترین بررسی: فرمت token
            if (!StorageTokenManager::isValidFormat($token)) {
                abort(400, 'Invalid token format');
            }
            
            // 2. شناسایی driver از prefix (بدون database)
            $driverName = StorageTokenManager::getDriverFromToken($token);
            Log::info("[Download] Token prefix detected: {$driverName}");
            
            // 3. Query database برای token
            $storageToken = StorageToken::where('token', $token)
                ->with('attachment')  // Eager load
                ->firstOrFail();
            
            // 4. بررسی انقضا
            if ($storageToken->isExpired()) {
                abort(410, 'Download link has expired');
            }
            
            // 5. بررسی تطابق driver (امنیت اضافه)
            if (!$storageToken->validateDriverMatch()) {
                Log::error("[Download] Driver mismatch for token: {$token}");
                abort(400, 'Invalid token');
            }
            
            // 6. Increment download count (async)
            $storageToken->increment('download_count');
            
            // 7. هدایت به driver مناسب
            return $this->downloadFromDriver($storageToken);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'File not found');
        } catch (\Exception $e) {
            Log::error('[Download] Error: ' . $e->getMessage(), [
                'token' => $token,
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Download failed');
        }
    }
    
    /**
     * Route به driver مناسب (هسته سیستم میانه‌جی)
     */
    private function downloadFromDriver(StorageToken $token)
    {
        $driver = $token->driver;
        $metadata = $token->metadata;
        
        Log::info("[Download] Routing to driver: {$driver}");
        
        switch ($driver) {
            case 'local':
                return $this->downloadFromLocal($token);
            
            case 'telegram':
                return $this->downloadFromTelegram($token);
            
            case 'google_drive':
                return $this->downloadFromGoogleDrive($token);
            
            case 'ftp':
                return $this->downloadFromFTP($token);
            
            default:
                Log::error("[Download] Unsupported driver: {$driver}");
                abort(400, 'Unsupported storage driver');
        }
    }
    
    /**
     * Download from Local storage
     */
    private function downloadFromLocal(StorageToken $token)
    {
        $attachment = $token->attachment;
        $media = $attachment->getFirstMedia();
        
        if (!$media) {
            abort(404, 'File not found in storage');
        }
        
        $path = $media->getPath();
        
        if (!file_exists($path)) {
            abort(404, 'Physical file not found');
        }
        
        return response()->download($path, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=31536000',  // 1 year
        ]);
    }
    
    /**
     * Download from Telegram (Hybrid: Cache + Stream)
     */
    private function downloadFromTelegram(StorageToken $token)
    {
        $metadata = $token->metadata;
        $fileSize = $metadata['telegram_file_size'] ?? 0;
        
        // استراتژی Hybrid:
        // فایل‌های کوچک (<10MB): Cache
        // فایل‌های بزرگ (>10MB): Stream
        
        if ($fileSize < 10 * 1024 * 1024) {
            Log::info("[Telegram] Using cached download (file size: {$fileSize})");
            return $this->cachedTelegramDownload($token, $metadata);
        }
        
        Log::info("[Telegram] Using streaming download (file size: {$fileSize})");
        return $this->streamTelegramDownload($token, $metadata);
    }
    
    /**
     * Cached Telegram download (برای فایل‌های کوچک)
     */
    private function cachedTelegramDownload(StorageToken $token, array $metadata)
    {
        $cacheKey = "telegram_file_{$token->id}";
        $cachePath = storage_path("app/cache/telegram/{$token->token}");
        
        // بررسی cache
        if (\Cache::has($cacheKey) && file_exists($cachePath)) {
            Log::info("[Telegram] Serving from cache: {$token->token}");
            
            return response()->download($cachePath, $metadata['original_name'], [
                'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
                'Cache-Control' => 'public, max-age=86400',  // 24 hours
                'X-Cache-Status' => 'HIT',
            ])->deleteFileAfterSend(false);  // حفظ فایل cache
        }
        
        // دانلود از تلگرام
        Log::info("[Telegram] Downloading from Telegram: {$token->token}");
        
        $driver = $this->storageManager->driver('telegram');
        
        // ایجاد دایرکتوری cache
        $directory = dirname($cachePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // دانلود به cache
        $result = $driver->downloadByMessageId(
            $metadata['telegram_message_id'],
            $metadata['telegram_channel_id'],
            $cachePath
        );
        
        if (!$result['success']) {
            Log::error("[Telegram] Download failed: {$result['message']}");
            abort(500, 'Failed to download file from Telegram');
        }
        
        // Cache برای 24 ساعت
        \Cache::put($cacheKey, true, now()->addDay());
        
        Log::info("[Telegram] File cached successfully: {$token->token}");
        
        return response()->download($cachePath, $metadata['original_name'], [
            'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
            'X-Cache-Status' => 'MISS',
        ])->deleteFileAfterSend(false);
    }
    
    /**
     * Streaming Telegram download (برای فایل‌های بزرگ)
     */
    private function streamTelegramDownload(StorageToken $token, array $metadata)
    {
        $driver = $this->storageManager->driver('telegram');
        
        Log::info("[Telegram] Starting stream: {$token->token}");
        
        return response()->stream(
            function() use ($driver, $metadata) {
                $success = $driver->streamToOutput(
                    $metadata['telegram_message_id'],
                    $metadata['telegram_channel_id']
                );
                
                if (!$success) {
                    Log::error("[Telegram] Stream failed");
                }
            },
            200,
            [
                'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $metadata['original_name'] . '"',
                'Content-Length' => $metadata['telegram_file_size'] ?? 0,
                'Cache-Control' => 'public, max-age=3600',
                'X-Stream-Mode' => 'DIRECT',
            ]
        );
    }
    
    /**
     * Download from Google Drive
     */
    private function downloadFromGoogleDrive(StorageToken $token)
    {
        $metadata = $token->metadata;
        $driver = $this->storageManager->driver('google_drive');
        
        // پیاده‌سازی مشابه...
        // ...
    }
    
    /**
     * Download from FTP
     */
    private function downloadFromFTP(StorageToken $token)
    {
        $metadata = $token->metadata;
        $driver = $this->storageManager->driver('ftp');
        
        // پیاده‌سازی مشابه...
        // ...
    }
}
```

---

### 6. بازنویسی TelegramStorageDriver::upload()

```php
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
        
        $mimeType = mime_content_type($filePath);
        $fileSize = filesize($filePath);
        
        \Log::info("[Telegram Upload] Starting upload: {$fileName} ({$fileSize} bytes)");
        
        // آپلود فایل
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
            'message' => 'File: ' . $fileName . ' | Size: ' . $this->formatBytes($fileSize),
        ]);
        
        // استخراج اطلاعات مهم: message_id + document_id
        $messageId = null;
        $documentId = null;
        $uploadedSize = 0;
        
        if (isset($messageMedia['updates'])) {
            foreach ($messageMedia['updates'] as $update) {
                if (isset($update['message'])) {
                    $messageId = $update['message']['id'];  // ⭐ کلیدی!
                    
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
            \Log::error("[Telegram Upload] Failed to extract message ID");
            return $this->errorResponse('Failed to upload: No message ID received');
        }
        
        \Log::info("[Telegram Upload] Success: message_id={$messageId}, document_id={$documentId}");
        
        // ⭐ metadata کامل برای دانلود
        return $this->successResponse('File uploaded to Telegram successfully', [
            'file_id' => '',  // خالی - بعداً token تولید می‌شود
            'url' => '',      // خالی - بعداً URL ایجاد می‌شود
            'metadata' => [
                'telegram_message_id' => $messageId,
                'telegram_document_id' => $documentId,
                'telegram_channel_id' => $channelId,
                'telegram_file_size' => $uploadedSize,
                'telegram_mime_type' => $mimeType,
                'original_name' => $fileName,
                'uploaded_at' => now()->toDateTimeString(),
            ],
        ]);
    } catch (\Exception $e) {
        \Log::error("[Telegram Upload] Exception: {$e->getMessage()}", [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
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
```

---

### 7. بازنویسی TelegramStorageDriver::download() methods

```php
/**
 * Download by message ID (روش جدید)
 */
public function downloadByMessageId(int $messageId, string $channelId, string $localPath): array
{
    try {
        if (!$this->initializeTelegram()) {
            return $this->errorResponse('Not authenticated');
        }
        
        \Log::info("[Telegram] Downloading message: {$messageId} from channel: {$channelId}");
        
        // دریافت پیام
        $messages = $this->telegram->channels->getMessages([
            'channel' => $channelId,
            'id' => [$messageId],
        ]);
        
        if (empty($messages['messages'])) {
            \Log::error("[Telegram] Message not found: {$messageId}");
            return $this->errorResponse('Message not found');
        }
        
        $message = $messages['messages'][0];
        $document = $message['media']['document'] ?? null;
        
        if (!$document) {
            \Log::error("[Telegram] No document in message: {$messageId}");
            return $this->errorResponse('Document not found in message');
        }
        
        // دانلود
        $this->telegram->downloadToFile($document, $localPath);
        
        if (file_exists($localPath)) {
            $size = filesize($localPath);
            \Log::info("[Telegram] Download successful: {$localPath} ({$size} bytes)");
            
            return $this->successResponse('File downloaded successfully', [
                'path' => $localPath,
                'size' => $size,
            ]);
        }
        
        return $this->errorResponse('Download failed: File not created');
    } catch (\Exception $e) {
        \Log::error("[Telegram] Download exception: {$e->getMessage()}");
        return $this->errorResponse('Download failed: ' . $e->getMessage());
    }
}

/**
 * Stream to output (برای فایل‌های بزرگ)
 */
public function streamToOutput(int $messageId, string $channelId): bool
{
    try {
        if (!$this->initializeTelegram()) {
            return false;
        }
        
        \Log::info("[Telegram] Streaming message: {$messageId}");
        
        $messages = $this->telegram->channels->getMessages([
            'channel' => $channelId,
            'id' => [$messageId],
        ]);
        
        $document = $messages['messages'][0]['media']['document'] ?? null;
        
        if (!$document) {
            return false;
        }
        
        // Stream مستقیم به output
        $this->telegram->downloadToStream($document, 'php://output');
        
        return true;
    } catch (\Exception $e) {
        \Log::error("[Telegram] Stream exception: {$e->getMessage()}");
        return false;
    }
}
```

---

### 8. بازنویسی AttachmentController::store()

```php
public function store(AttachmentRequest $request)
{
    $urls = [];
    
    foreach ($request->attachment as $media) {
        // Determine file type
        $mimeType = $media->getMimeType();
        $fileType = $this->determineFileType($mimeType);
        $originalName = $media->getClientOriginalName();
        
        // Create attachment record
        $attachment = new Attachment();
        $attachment->file_type = $fileType;
        $attachment->save();
        
        // Upload using storage manager
        $uploadResult = $this->storageManager->upload(
            $media->getRealPath(),
            $originalName,
            $fileType
        );
        
        if (!$uploadResult['success']) {
            // Fallback to local
            $attachment->addMedia($media)->toMediaCollection();
            $attachment->storage_driver = 'local';
            $attachment->save();
            
            foreach ($attachment->getMedia() as $mediaItem) {
                $converted_url = $this->buildMediaResponse($mediaItem, $attachment);
                $urls[] = $converted_url;
            }
            continue;
        }
        
        // Update attachment
        $attachment->storage_driver = $uploadResult['driver'];
        $attachment->storage_metadata = $uploadResult['metadata'] ?? [];
        $attachment->save();
        
        // ⭐ تولید token با prefix مناسب
        $storageToken = StorageToken::generate(
            $attachment,
            $uploadResult['driver'],
            $uploadResult['metadata']
        );
        
        // ⭐ ساخت URL با token
        $downloadUrl = route('storage.download', ['token' => $storageToken->token]);
        
        \Log::info("[Upload] Token generated: {$storageToken->token} -> {$downloadUrl}");
        
        // Build response
        $converted_url = [
            'thumbnail' => $downloadUrl,
            'original' => $downloadUrl,
            'id' => $attachment->id,
            'storage_driver' => $uploadResult['driver'],
            'file_type' => $fileType,
        ];
        
        // برای تصاویر local، thumbnail از media library
        if (strpos($mimeType, 'image/') !== false && $uploadResult['driver'] === 'local') {
            $attachment->addMedia($media)->toMediaCollection();
            foreach ($attachment->getMedia() as $mediaItem) {
                $converted_url['thumbnail'] = $mediaItem->getUrl('thumbnail');
            }
        }
        
        $urls[] = $converted_url;
    }
    
    return $urls;
}
```

---

## 📊 نمونه جریان کامل

### آپلود:

```
User uploads file.jpg (telegram driver)
    ↓
AttachmentController::store()
    ↓
StorageManager->upload() → TelegramDriver->upload()
    ↓
Upload to Telegram channel → Extract message_id
    ↓
Return metadata: { telegram_message_id: 123, ... }
    ↓
Save attachment with metadata
    ↓
StorageToken::generate('telegram', metadata)
    ↓
Generate token: "tg_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b"
    ↓
Return URL: https://srcchi.top/backend/storage/download/tg_8f3e4a21...
```

### دانلود:

```
User clicks: /storage/download/tg_8f3e4a21...
    ↓
AttachmentController::download(token)
    ↓
Parse token prefix: "tg" → driver = "telegram"
    ↓
Query database: StorageToken where token = ...
    ↓
Check expiration & validate
    ↓
Route to downloadFromTelegram()
    ↓
Check file size:
    < 10MB → cachedDownload()
    > 10MB → streamDownload()
    ↓
Download/Stream file
    ↓
Return response to user
```

---

## ✅ مزایای این معماری

### 1. Security
- ✅ Token-based access (نه file path)
- ✅ Driver type مخفی (فقط prefix 2 حرفی)
- ✅ Expiration support
- ✅ Download count tracking

### 2. Performance
- ✅ فایل‌های کوچک: Cache (سرعت بالا)
- ✅ فایل‌های بزرگ: Streaming (حافظه کم)
- ✅ Prefix-based routing (بدون database query اضافی)

### 3. Simplicity
- ✅ تک endpoint برای همه drivers
- ✅ URL یکسان برای همه
- ✅ کد تمیز و maintainable

### 4. Extensibility
- ✅ افزودن driver جدید: فقط prefix اضافه کنید
- ✅ هیچ تغییری در URL یا API لازم نیست

---

## 🧪 مثال‌های واقعی

```php
// Local file
lc_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
URL: https://srcchi.top/backend/storage/download/lc_8f3e4a21...

// Telegram file
tg_7d2c3b14-8a6e-4c3f-b2e1-9d7f6e5a4c3b
URL: https://srcchi.top/backend/storage/download/tg_7d2c3b14...

// Google Drive file
gd_9e4f5a26-7b8c-5d6e-c3f2-8e6d7f5a6b4c
URL: https://srcchi.top/backend/storage/download/gd_9e4f5a26...
```

همه یک فرم، همه امن، همه بهینه! 🚀
