# پلن جامع بهبود سیستم ذخیره‌سازی تلگرام

## 📋 خلاصه مشکلات فعلی

### 1. مشکلات آپلود:
- ✅ آپلود کار می‌کند
- ⚠️ فقط `document_id` ذخیره می‌شود
- ❌ `message_id` ذخیره نمی‌شود (برای دانلود ضروری است)
- ❌ `channel_id` در metadata هست ولی به درستی استفاده نمی‌شود

### 2. مشکلات دانلود:
- ❌ متد `download()` کار نمی‌کند
- ❌ استفاده از `downloadToFile($fileId)` که با document ID کار نمی‌کند
- ❌ نیاز به `message_id` + `channel_id` برای دانلود
- ❌ لینک‌ها به صورت `telegram://file/{$fileId}` هستند

### 3. مشکلات getFileUrl():
- ❌ فقط یک route برمی‌گرداند که پیاده‌سازی نشده
- ❌ لینک‌ها باید شبیه local باشند
- ❌ لینک‌ها نباید اطلاعات محل ذخیره را فاش کنند

### 4. مشکلات delete():
- ❌ اصلاً پیاده‌سازی نشده
- ⚠️ حذف واقعی در تلگرام نیاز به message_id دارد

---

## 🎯 اهداف بهبود

### 1. Performance
- استفاده از **streaming** برای فایل‌های بزرگ (کاهش استفاده از حافظه)
- **Cache** کردن فایل‌های پرتکرار (کاهش بار سرور تلگرام)
- **Chunked download** برای سرعت بهتر
- **Lazy loading** برای تصاویر

### 2. Security
- لینک‌های **signed URL** با expire time
- **Token-based** authentication برای دانلود
- عدم افشای `file_id`, `message_id`, `channel_id` در URL
- **Rate limiting** برای جلوگیری از abuse

### 3. Reliability
- **Fallback** به local در صورت خطا
- **Retry mechanism** برای دانلود
- **Queue** برای آپلود فایل‌های بزرگ
- **Health check** برای اتصال تلگرام

---

## 🏗️ معماری پیشنهادی

### 1. ساختار داده جدید

```php
// در TelegramStorageDriver::upload()
return [
    'success' => true,
    'file_id' => $uniqueToken,  // Token یکتا (UUID)
    'url' => route('storage.download', ['token' => $uniqueToken]),
    'driver' => 'telegram',
    'metadata' => [
        'telegram_message_id' => $messageId,      // ✅ جدید
        'telegram_document_id' => $documentId,    // موجود
        'telegram_channel_id' => $channelId,      // موجود
        'telegram_file_size' => $fileSize,
        'telegram_mime_type' => $mimeType,
        'original_name' => $fileName,
        'uploaded_at' => now(),
    ],
];
```

### 2. جدول جدید برای توکن‌ها

```php
// Migration: create_storage_tokens_table.php
Schema::create('storage_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();           // UUID
    $table->unsignedBigInteger('attachment_id');     // FK to attachments
    $table->string('driver');                        // telegram, google_drive, etc
    $table->json('metadata');                        // Driver-specific data
    $table->timestamp('expires_at')->nullable();    // برای signed URLs
    $table->integer('download_count')->default(0);  // تعداد دانلود
    $table->timestamps();
    
    $table->foreign('attachment_id')
        ->references('id')
        ->on('attachments')
        ->onDelete('cascade');
    
    $table->index('token');
    $table->index(['driver', 'attachment_id']);
});
```

**مزایا:**
- 🔒 Security: توکن به جای file_id در URL
- 📊 Analytics: می‌توانیم تعداد دانلود را track کنیم
- ⏱️ Expiration: لینک‌های موقت برای فایل‌های حساس
- 🔄 Revocation: می‌توانیم دسترسی را لغو کنیم

### 3. سیستم دانلود جدید

#### الف) Route جدید:

```php
// routes/api.php
Route::get('/storage/download/{token}', [AttachmentController::class, 'downloadByToken'])
    ->name('storage.download');

// OR برای streaming:
Route::get('/storage/stream/{token}', [AttachmentController::class, 'streamFile'])
    ->name('storage.stream');
```

#### ب) Controller جدید:

```php
// AttachmentController.php
public function downloadByToken(Request $request, string $token)
{
    // 1. Validate token
    $storageToken = StorageToken::where('token', $token)
        ->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->firstOrFail();
    
    // 2. Get attachment
    $attachment = $storageToken->attachment;
    
    // 3. Increment download count
    $storageToken->increment('download_count');
    
    // 4. Download from appropriate driver
    if ($attachment->storage_driver === 'telegram') {
        return $this->downloadFromTelegram($storageToken);
    }
    
    // ... other drivers
}
```

---

## 🚀 پیاده‌سازی بهینه برای تلگرام

### روش 1: Direct Download (ساده، سریع)

```php
public function downloadFromTelegram(StorageToken $token): StreamedResponse
{
    $metadata = $token->metadata;
    $messageId = $metadata['telegram_message_id'];
    $channelId = $metadata['telegram_channel_id'];
    
    if (!$this->initializeTelegram()) {
        abort(503, 'Telegram service unavailable');
    }
    
    // Get message
    $messages = $this->telegram->channels->getMessages([
        'channel' => $channelId,
        'id' => [$messageId],
    ]);
    
    $document = $messages['messages'][0]['media']['document'] ?? null;
    if (!$document) {
        abort(404, 'File not found');
    }
    
    // Stream download
    return response()->stream(function() use ($document) {
        $this->telegram->downloadToStream($document, 'php://output');
    }, 200, [
        'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="' . $metadata['original_name'] . '"',
        'Content-Length' => $metadata['telegram_file_size'] ?? 0,
        'Cache-Control' => 'public, max-age=3600',
    ]);
}
```

**مزایا:**
- ✅ استفاده کمتر از حافظه (streaming)
- ✅ سرعت بالا (direct stream)
- ✅ مناسب برای فایل‌های بزرگ

**معایب:**
- ❌ نیاز به اتصال دائمی تلگرام
- ❌ اگر کاربران زیاد باشند، ممکن است rate limit شویم

---

### روش 2: Cached Download (توصیه می‌شود) ⭐

```php
public function downloadFromTelegram(StorageToken $token): BinaryFileResponse
{
    $metadata = $token->metadata;
    $cacheKey = 'telegram_file_' . $token->id;
    $cachePath = storage_path('app/cache/telegram/' . $token->token);
    
    // 1. Check if cached
    if (Cache::has($cacheKey) && file_exists($cachePath)) {
        Log::info('[Telegram] Serving cached file: ' . $token->token);
        return response()->download($cachePath, $metadata['original_name'], [
            'Content-Type' => $metadata['telegram_mime_type'],
            'Cache-Control' => 'public, max-age=86400',
        ])->deleteFileAfterSend(false);  // Keep cached file
    }
    
    // 2. Download from Telegram
    if (!$this->initializeTelegram()) {
        abort(503, 'Telegram service unavailable');
    }
    
    $messageId = $metadata['telegram_message_id'];
    $channelId = $metadata['telegram_channel_id'];
    
    // Get message
    $messages = $this->telegram->channels->getMessages([
        'channel' => $channelId,
        'id' => [$messageId],
    ]);
    
    $document = $messages['messages'][0]['media']['document'] ?? null;
    if (!$document) {
        abort(404, 'File not found');
    }
    
    // 3. Download to cache
    $directory = dirname($cachePath);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $this->telegram->downloadToFile($document, $cachePath);
    
    // 4. Cache for 24 hours
    Cache::put($cacheKey, true, now()->addDay());
    
    // 5. Return file
    return response()->download($cachePath, $metadata['original_name'], [
        'Content-Type' => $metadata['telegram_mime_type'],
        'Cache-Control' => 'public, max-age=86400',
    ])->deleteFileAfterSend(false);
}
```

**مزایا:**
- ✅ کاهش بار روی تلگرام (cache)
- ✅ سرعت بالا برای دانلودهای مکرر
- ✅ قابلیت purge کردن cache
- ✅ مناسب برای production

**Cache Management:**
```php
// Artisan command برای پاک کردن cache های قدیمی
php artisan telegram:clear-cache --older-than=7days
```

---

### روش 3: Hybrid (بهترین) 🏆

```php
public function downloadFromTelegram(StorageToken $token): Response
{
    $fileSize = $token->metadata['telegram_file_size'] ?? 0;
    
    // فایل‌های کوچک (<10MB): Cache شوند
    if ($fileSize < 10 * 1024 * 1024) {
        return $this->cachedDownload($token);
    }
    
    // فایل‌های بزرگ (>10MB): Stream شوند
    return $this->streamDownload($token);
}

private function cachedDownload(StorageToken $token): BinaryFileResponse
{
    // روش 2 (Cached)
}

private function streamDownload(StorageToken $token): StreamedResponse
{
    // روش 1 (Direct Stream)
}
```

**مزایا:**
- ✅ بهترین performance برای فایل‌های کوچک (cache)
- ✅ بهترین استفاده از حافظه برای فایل‌های بزرگ (stream)
- ✅ انعطاف‌پذیری بالا

---

## 📊 مقایسه روش‌ها

| معیار | Direct Stream | Cached | Hybrid |
|-------|--------------|--------|---------|
| **حافظه** | کم (streaming) | متوسط (cached files) | بهینه |
| **سرعت اولین دانلود** | سریع | سریع | سریع |
| **سرعت دانلودهای بعدی** | سریع | خیلی سریع ⚡ | خیلی سریع ⚡ |
| **بار روی تلگرام** | زیاد | کم | کم |
| **فضای دیسک** | صفر | زیاد | متوسط |
| **مناسب برای** | Demo | Production | Production ⭐ |

---

## 🔧 تغییرات مورد نیاز

### 1. Migration: اضافه کردن جدول tokens

```bash
php artisan make:migration create_storage_tokens_table
```

### 2. Model: StorageToken

```php
namespace Marvel\Database\Models;

class StorageToken extends Model
{
    protected $fillable = ['token', 'attachment_id', 'driver', 'metadata', 'expires_at'];
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];
    
    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }
    
    public static function generate(Attachment $attachment, string $driver, array $metadata, ?int $expiresIn = null)
    {
        return self::create([
            'token' => Str::uuid(),
            'attachment_id' => $attachment->id,
            'driver' => $driver,
            'metadata' => $metadata,
            'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
        ]);
    }
}
```

### 3. بازنویسی TelegramStorageDriver::upload()

```php
public function upload(string $filePath, string $fileName, string $type = 'image'): array
{
    // ... existing code ...
    
    // Extract ALL needed info
    $messageId = null;
    $documentId = null;
    $fileSize = 0;
    $mimeType = mime_content_type($filePath);
    
    if (isset($messageMedia['updates'])) {
        foreach ($messageMedia['updates'] as $update) {
            if (isset($update['message'])) {
                $messageId = $update['message']['id'];  // ⭐ این کلیدی است!
                
                if (isset($update['message']['media']['document'])) {
                    $document = $update['message']['media']['document'];
                    $documentId = $document['id'];
                    $fileSize = $document['size'] ?? 0;
                    
                    // Extract MIME type
                    foreach ($document['attributes'] ?? [] as $attr) {
                        if ($attr['_'] === 'documentAttributeFilename') {
                            // Already have filename
                        }
                    }
                }
                break;
            }
        }
    }
    
    if (!$messageId) {
        return $this->errorResponse('Failed to extract message ID');
    }
    
    return $this->successResponse('File uploaded successfully', [
        'file_id' => Str::uuid(),  // ⭐ Token به جای document ID
        'url' => '',  // خالی - بعداً توسط AttachmentController پر می‌شود
        'metadata' => [
            'telegram_message_id' => $messageId,
            'telegram_document_id' => $documentId,
            'telegram_channel_id' => $channelId,
            'telegram_file_size' => $fileSize,
            'telegram_mime_type' => $mimeType,
            'original_name' => $fileName,
            'uploaded_at' => now()->toDateTimeString(),
        ],
    ]);
}
```

### 4. بازنویسی TelegramStorageDriver::download()

```php
public function download(string $token, string $localPath = null): array
{
    try {
        // این متد الان deprecated است - باید از downloadByMessageId استفاده شود
        throw new \Exception('Use downloadByMessageId() instead');
    } catch (\Exception $e) {
        return $this->errorResponse($e->getMessage());
    }
}

/**
 * Download file by message ID (روش جدید)
 */
public function downloadByMessageId(int $messageId, string $channelId, string $localPath): array
{
    try {
        if (!$this->initializeTelegram()) {
            return $this->errorResponse('Not authenticated');
        }
        
        // Get message
        $messages = $this->telegram->channels->getMessages([
            'channel' => $channelId,
            'id' => [$messageId],
        ]);
        
        if (empty($messages['messages'])) {
            return $this->errorResponse('Message not found');
        }
        
        $message = $messages['messages'][0];
        $document = $message['media']['document'] ?? null;
        
        if (!$document) {
            return $this->errorResponse('Document not found in message');
        }
        
        // Download
        $this->telegram->downloadToFile($document, $localPath);
        
        if (file_exists($localPath)) {
            return $this->successResponse('File downloaded successfully', [
                'path' => $localPath,
                'size' => filesize($localPath),
            ]);
        }
        
        return $this->errorResponse('Download failed: File not created');
    } catch (\Exception $e) {
        return $this->errorResponse('Download failed: ' . $e->getMessage());
    }
}

/**
 * Stream file directly (روش streaming)
 */
public function streamToOutput(int $messageId, string $channelId): bool
{
    try {
        if (!$this->initializeTelegram()) {
            return false;
        }
        
        $messages = $this->telegram->channels->getMessages([
            'channel' => $channelId,
            'id' => [$messageId],
        ]);
        
        $document = $messages['messages'][0]['media']['document'] ?? null;
        if (!$document) {
            return false;
        }
        
        // Stream to php://output
        $this->telegram->downloadToStream($document, 'php://output');
        return true;
    } catch (\Exception $e) {
        Log::error('[Telegram] Stream failed: ' . $e->getMessage());
        return false;
    }
}
```

### 5. بازنویسی AttachmentController::store()

```php
public function store(AttachmentRequest $request)
{
    $urls = [];
    
    foreach ($request->attachment as $media) {
        // ... existing code ...
        
        if (!$uploadResult['success']) {
            // ... fallback ...
            continue;
        }
        
        // Update attachment
        $attachment->storage_driver = $uploadResult['driver'];
        $attachment->storage_metadata = $uploadResult['metadata'] ?? [];
        $attachment->save();
        
        // ⭐ ایجاد token
        $storageToken = StorageToken::generate(
            $attachment,
            $uploadResult['driver'],
            $uploadResult['metadata']
        );
        
        // ⭐ Build URL with token
        $downloadUrl = route('storage.download', ['token' => $storageToken->token]);
        
        $converted_url = [
            'thumbnail' => $downloadUrl,  // ⭐ URL واقعی
            'original' => $downloadUrl,   // ⭐ URL واقعی
            'id' => $attachment->id,
            'storage_driver' => $uploadResult['driver'],
            'file_type' => $fileType,
        ];
        
        $urls[] = $converted_url;
    }
    
    return $urls;
}
```

### 6. اضافه کردن متد downloadByToken

```php
/**
 * Download file by token (امن و یکسان برای تمام drivers)
 */
public function downloadByToken(string $token)
{
    try {
        // 1. Validate token
        $storageToken = StorageToken::where('token', $token)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();
        
        // 2. Increment download count
        $storageToken->increment('download_count');
        
        // 3. Get attachment
        $attachment = $storageToken->attachment;
        $metadata = $storageToken->metadata;
        
        // 4. Download based on driver
        switch ($attachment->storage_driver) {
            case 'telegram':
                return $this->downloadFromTelegram($storageToken, $metadata);
            
            case 'local':
                return $this->downloadFromLocal($attachment);
            
            // ... other drivers
            
            default:
                abort(400, 'Unsupported storage driver');
        }
    } catch (ModelNotFoundException $e) {
        abort(404, 'File not found or expired');
    } catch (\Exception $e) {
        Log::error('[Download] Failed: ' . $e->getMessage());
        abort(500, 'Download failed');
    }
}

/**
 * Download from Telegram (Hybrid approach)
 */
private function downloadFromTelegram(StorageToken $token, array $metadata)
{
    $fileSize = $metadata['telegram_file_size'] ?? 0;
    
    // Small files: Cache them
    if ($fileSize < 10 * 1024 * 1024) { // 10MB
        return $this->cachedTelegramDownload($token, $metadata);
    }
    
    // Large files: Stream them
    return $this->streamTelegramDownload($token, $metadata);
}

/**
 * Cached download (برای فایل‌های کوچک)
 */
private function cachedTelegramDownload(StorageToken $token, array $metadata)
{
    $cacheKey = 'telegram_file_' . $token->id;
    $cachePath = storage_path('app/cache/telegram/' . $token->token);
    
    // Check cache
    if (Cache::has($cacheKey) && file_exists($cachePath)) {
        Log::info('[Telegram] Serving cached file: ' . $token->token);
        return response()->download($cachePath, $metadata['original_name'], [
            'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ])->deleteFileAfterSend(false);
    }
    
    // Download from Telegram
    $driver = $this->storageManager->driver('telegram');
    
    $directory = dirname($cachePath);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    $result = $driver->downloadByMessageId(
        $metadata['telegram_message_id'],
        $metadata['telegram_channel_id'],
        $cachePath
    );
    
    if (!$result['success']) {
        abort(500, $result['message']);
    }
    
    // Cache for 24 hours
    Cache::put($cacheKey, true, now()->addDay());
    
    return response()->download($cachePath, $metadata['original_name'], [
        'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
        'Cache-Control' => 'public, max-age=86400',
    ])->deleteFileAfterSend(false);
}

/**
 * Stream download (برای فایل‌های بزرگ)
 */
private function streamTelegramDownload(StorageToken $token, array $metadata)
{
    $driver = $this->storageManager->driver('telegram');
    
    return response()->stream(function() use ($driver, $metadata) {
        $driver->streamToOutput(
            $metadata['telegram_message_id'],
            $metadata['telegram_channel_id']
        );
    }, 200, [
        'Content-Type' => $metadata['telegram_mime_type'] ?? 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="' . $metadata['original_name'] . '"',
        'Content-Length' => $metadata['telegram_file_size'] ?? 0,
        'Cache-Control' => 'public, max-age=3600',
    ]);
}
```

---

## 🧹 Maintenance: Cache Management

### Artisan Command برای پاک کردن cache

```php
// app/Console/Commands/ClearTelegramCache.php
namespace App\Console\Commands;

class ClearTelegramCache extends Command
{
    protected $signature = 'telegram:clear-cache 
                          {--older-than=7 : Days older than}
                          {--all : Clear all cached files}';
    
    public function handle()
    {
        $path = storage_path('app/cache/telegram');
        
        if ($this->option('all')) {
            // Delete all
            $count = 0;
            foreach (glob($path . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            $this->info("Deleted {$count} cached files");
            return;
        }
        
        // Delete old files
        $olderThan = (int) $this->option('older-than');
        $threshold = now()->subDays($olderThan);
        
        $count = 0;
        foreach (glob($path . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $threshold->timestamp) {
                unlink($file);
                Cache::forget('telegram_file_' . basename($file));
                $count++;
            }
        }
        
        $this->info("Deleted {$count} cached files older than {$olderThan} days");
    }
}
```

**استفاده:**
```bash
# پاک کردن فایل‌های بیش از 7 روز
php artisan telegram:clear-cache

# پاک کردن فایل‌های بیش از 30 روز
php artisan telegram:clear-cache --older-than=30

# پاک کردن همه
php artisan telegram:clear-cache --all
```

---

## 📈 بهینه‌سازی‌های اضافی

### 1. Image Thumbnails

برای تصاویر، می‌توانیم thumbnail ایجاد کنیم:

```php
public function generateThumbnail(StorageToken $token)
{
    if ($token->metadata['telegram_mime_type'] !== 'image/jpeg' && 
        $token->metadata['telegram_mime_type'] !== 'image/png') {
        return null;
    }
    
    $thumbnailPath = storage_path('app/cache/telegram/thumb_' . $token->token . '.jpg');
    
    if (file_exists($thumbnailPath)) {
        return $thumbnailPath;
    }
    
    // Download original
    $originalPath = storage_path('app/cache/telegram/' . $token->token);
    // ... download if not cached ...
    
    // Create thumbnail
    $image = \Intervention\Image\Facades\Image::make($originalPath);
    $image->fit(300, 300);
    $image->save($thumbnailPath, 80);
    
    return $thumbnailPath;
}
```

### 2. Progress Tracking (آپلود/دانلود)

```php
// Using MadelineProto callback
$this->telegram->downloadToFile($document, $localPath, function($progress) {
    // Update progress in cache or database
    Cache::put('download_progress_' . $token, $progress, 60);
});
```

### 3. Queue برای آپلود فایل‌های بزرگ

```php
// Job برای آپلود async
dispatch(new UploadToTelegramJob($attachment, $filePath));
```

---

## ✅ Checklist پیاده‌سازی

### Backend:
- [ ] ایجاد migration برای جدول storage_tokens
- [ ] ایجاد Model StorageToken
- [ ] بازنویسی TelegramStorageDriver::upload() (ذخیره message_id)
- [ ] بازنویسی TelegramStorageDriver::download() (استفاده از message_id)
- [ ] اضافه کردن متد downloadByMessageId()
- [ ] اضافه کردن متد streamToOutput()
- [ ] بازنویسی AttachmentController::store() (ایجاد token)
- [ ] اضافه کردن AttachmentController::downloadByToken()
- [ ] اضافه کردن متدهای cachedDownload و streamDownload
- [ ] اضافه کردن route جدید برای download
- [ ] اضافه کردن Artisan command برای cache management
- [ ] تست آپلود/دانلود

### Frontend:
- [ ] بررسی استفاده از URL های جدید
- [ ] اضافه کردن progress bar (اختیاری)
- [ ] تست در browser

### Testing:
- [ ] تست آپلود فایل کوچک (<1MB)
- [ ] تست آپلود فایل متوسط (10MB)
- [ ] تست آپلود فایل بزرگ (100MB+)
- [ ] تست دانلود اولین بار (بدون cache)
- [ ] تست دانلود دومین بار (با cache)
- [ ] تست streaming برای فایل بزرگ
- [ ] تست expiration برای token
- [ ] تست delete
- [ ] تست performance

---

## 📊 مقایسه قبل و بعد

| ویژگی | قبل | بعد |
|-------|-----|-----|
| **آپلود** | ✅ کار می‌کند | ✅ بهبود یافته (message_id) |
| **دانلود** | ❌ کار نمی‌کند | ✅ کار می‌کند (cache/stream) |
| **URL** | `telegram://file/{id}` | `/storage/download/{token}` |
| **Security** | ❌ file_id در URL | ✅ token در URL |
| **Performance (فایل کوچک)** | N/A | ⚡ خیلی سریع (cache) |
| **Performance (فایل بزرگ)** | N/A | ⚡ سریع (streaming) |
| **حافظه** | N/A | 📉 کم (streaming) |
| **Delete** | ❌ کار نمی‌کند | ✅ کار می‌کند |

---

## 🎯 نتیجه‌گیری

این پلن یک راه‌حل **جامع، امن و بهینه** برای سیستم ذخیره‌سازی تلگرام ارائه می‌دهد:

✅ **Security**: لینک‌های امن با token  
✅ **Performance**: Cache + Streaming  
✅ **Scalability**: قابل توسعه برای drivers دیگر  
✅ **Maintainability**: کد تمیز و قابل نگهداری  
✅ **User Experience**: لینک‌های ساده و یکسان  

با این معماری، سیستم شما به بهترین شکل ممکن کار خواهد کرد! 🚀
