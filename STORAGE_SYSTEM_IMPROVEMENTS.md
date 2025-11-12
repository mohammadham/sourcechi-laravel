# بهبودهای سیستم ذخیره‌سازی - رفع نقاط ضعف

## 🔍 تحلیل مشکلات شناسایی شده

### 1. ❌ Google Drive و FTP - ناقص
**وضعیت فعلی:** فقط placeholder (501 error)
**راه‌حل:** پیاده‌سازی کامل یا حذف از لیست drivers

### 2. ⚠️ Expiration Support - غیرفعال
**وضعیت فعلی:** فیلد `expires_at` وجود دارد ولی همیشه `null` است
**راه‌حل:** 
- افزودن تنظیمات expiration در admin panel
- پیاده‌سازی expiration برای هر driver
- Cleanup خودکار tokenهای منقضی شده

### 3. ❌ Backward Compatibility - نقص بحرانی
**وضعیت فعلی:** 
- فایل‌های قدیمی local که storage_token ندارند کار نمی‌کنند
- لینک‌های قدیمی که به صورت مستقیم از Spatie Media Library هستند شکسته می‌شوند

**راه‌حل:**
- Migration برای ایجاد token برای attachmentهای قدیمی
- Fallback mechanism در AttachmentController::download()
- Route جدید برای لینک‌های قدیمی

### 4. ✅ UI برای Cache Management
**نیاز:** دکمه در admin panel برای پاک کردن cache

---

## 🛠️ راه‌حل‌ها

### راه‌حل 1: Backward Compatibility (اولویت بالا) 🚨

#### الف) Migration برای ایجاد token برای attachments قدیمی

```php
// Migration: 2025_01_10_000002_create_tokens_for_existing_attachments.php

public function up()
{
    // پیدا کردن تمام attachments که storage_token ندارند
    $attachments = DB::table('attachments')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('storage_tokens')
                ->whereColumn('storage_tokens.attachment_id', 'attachments.id');
        })
        ->get();
    
    foreach ($attachments as $attachment) {
        $driver = $attachment->storage_driver ?? 'local';
        $metadata = json_decode($attachment->storage_metadata, true) ?? [];
        
        // تولید token
        $prefix = match($driver) {
            'local' => 'lc',
            'telegram' => 'tg',
            'google_drive' => 'gd',
            'ftp' => 'ft',
            default => 'lc',
        };
        
        $token = $prefix . '_' . (string) Str::uuid();
        
        DB::table('storage_tokens')->insert([
            'token' => $token,
            'attachment_id' => $attachment->id,
            'driver' => $driver,
            'metadata' => json_encode($metadata),
            'expires_at' => null,
            'download_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

#### ب) اصلاح downloadFromLocal برای پشتیبانی از لینک‌های قدیمی

```php
private function downloadFromLocal(StorageToken $token)
{
    $attachment = $token->attachment;
    
    // روش جدید: استفاده از Spatie Media Library
    $media = $attachment->getFirstMedia();
    
    if ($media) {
        $path = $media->getPath();
        
        if (file_exists($path)) {
            return response()->download($path, $media->file_name, [
                'Content-Type' => $media->mime_type,
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        }
    }
    
    // Fallback: سیستم قدیمی
    // اگر فایل در media library نبود، شاید در metadata باشد
    $metadata = $token->metadata;
    if (isset($metadata['path']) && file_exists($metadata['path'])) {
        return response()->download($metadata['path']);
    }
    
    abort(404, 'File not found');
}
```

#### ج) Route اضافی برای لینک‌های قدیمی Spatie

```php
// در routes/api.php
Route::get('/media/{id}/{filename}', function($id, $filename) {
    // پیدا کردن attachment
    $attachment = Attachment::findOrFail($id);
    
    // اگر token دارد، redirect کن
    $storageToken = StorageToken::where('attachment_id', $id)->first();
    if ($storageToken) {
        return redirect()->route('storage.download', ['token' => $storageToken->token]);
    }
    
    // در غیر اینصورت، دانلود مستقیم
    $media = $attachment->getFirstMedia();
    if ($media) {
        return response()->download($media->getPath(), $media->file_name);
    }
    
    abort(404);
});
```

---

### راه‌حل 2: Expiration Support (قابل تنظیم)

#### الف) تنظیمات در config/shop.php

```php
'storage' => [
    'expiration' => [
        'enabled' => env('STORAGE_EXPIRATION_ENABLED', false),
        'default_ttl' => env('STORAGE_EXPIRATION_TTL', 86400), // 24 hours
        'per_driver' => [
            'local' => null,        // هیچوقت expire نمی‌شود
            'telegram' => 86400,    // 24 ساعت
            'google_drive' => 3600, // 1 ساعت
            'ftp' => 7200,          // 2 ساعت
        ],
    ],
],
```

#### ب) اضافه کردن در تنظیمات Admin Panel

```tsx
// در admin/src/components/settings/storage/index.tsx

// فیلد جدید
<Card className="mb-5">
  <Label>{t('form:storage-expiration-settings')}</Label>
  <div className="space-y-4">
    <Checkbox
      {...register('storage.expiration.enabled')}
      label={t('form:storage-expiration-enabled')}
    />
    
    {expirationEnabled && (
      <>
        <Input
          label={t('form:storage-expiration-default-ttl')}
          {...register('storage.expiration.default_ttl')}
          type="number"
          placeholder="86400"
        />
        
        <Alert variant="info">
          {t('form:storage-expiration-help-text')}
        </Alert>
      </>
    )}
  </div>
</Card>
```

#### ج) استفاده در StorageToken::generate()

```php
public static function generate(
    Attachment $attachment,
    string $driver,
    array $metadata,
    ?int $expiresIn = null
): self {
    $token = StorageTokenManager::generateToken($driver);
    
    // اگر expiration فعال باشد
    $config = config('shop.storage.expiration', []);
    if ($config['enabled'] ?? false) {
        // اگر TTL مشخص نشده، از تنظیمات driver استفاده کن
        if ($expiresIn === null) {
            $expiresIn = $config['per_driver'][$driver] 
                ?? $config['default_ttl'] 
                ?? null;
        }
    }
    
    return self::create([
        'token' => $token,
        'attachment_id' => $attachment->id,
        'driver' => $driver,
        'metadata' => $metadata,
        'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
    ]);
}
```

#### د) Artisan Command برای cleanup tokenهای منقضی

```php
// ClearExpiredTokens.php
public function handle()
{
    $expired = StorageToken::where('expires_at', '<', now())->get();
    
    $count = 0;
    foreach ($expired as $token) {
        // پاک کردن cache
        Cache::forget("telegram_file_{$token->id}");
        
        // پاک کردن فایل cache
        $cachePath = storage_path("app/cache/telegram/{$token->token}");
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
        
        // حذف token
        $token->delete();
        $count++;
    }
    
    $this->info("Deleted {$count} expired tokens");
}
```

---

### راه‌حل 3: تکمیل Google Drive و FTP

#### استراتژی:

**الف) Google Drive:**
```php
private function downloadFromGoogleDrive(StorageToken $token)
{
    $metadata = $token->metadata;
    $fileId = $metadata['google_drive_file_id'] ?? null;
    
    if (!$fileId) {
        abort(404, 'Google Drive file ID not found');
    }
    
    $driver = $this->storageManager->driver('google_drive');
    
    // دانلود موقت
    $tempPath = storage_path('app/temp/' . $token->token);
    $result = $driver->download($fileId, $tempPath);
    
    if (!$result['success']) {
        abort(500, 'Failed to download from Google Drive');
    }
    
    return response()->download($tempPath, $metadata['original_name'])
        ->deleteFileAfterSend(true);
}
```

**ب) FTP:**
```php
private function downloadFromFTP(StorageToken $token)
{
    $metadata = $token->metadata;
    $ftpPath = $metadata['ftp_path'] ?? null;
    
    if (!$ftpPath) {
        abort(404, 'FTP path not found');
    }
    
    $driver = $this->storageManager->driver('ftp');
    
    // دانلود موقت
    $tempPath = storage_path('app/temp/' . $token->token);
    $result = $driver->download($ftpPath, $tempPath);
    
    if (!$result['success']) {
        abort(500, 'Failed to download from FTP');
    }
    
    return response()->download($tempPath, $metadata['original_name'])
        ->deleteFileAfterSend(true);
}
```

---

### راه‌حل 4: UI برای Cache Management در Admin

#### الف) API Endpoint جدید

```php
// StorageController.php

/**
 * Clear Telegram cache
 */
public function clearTelegramCache(Request $request)
{
    try {
        $olderThan = $request->input('older_than', 7);
        $all = $request->input('all', false);
        
        // اجرای command
        $command = $all 
            ? 'telegram:clear-cache --all'
            : "telegram:clear-cache --older-than={$olderThan}";
        
        Artisan::call($command);
        $output = Artisan::output();
        
        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
            'output' => $output,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Get cache statistics
 */
public function getCacheStats()
{
    $path = storage_path('app/cache/telegram');
    
    if (!is_dir($path)) {
        return response()->json([
            'success' => true,
            'data' => [
                'total_files' => 0,
                'total_size' => 0,
            ],
        ]);
    }
    
    $files = glob($path . '/*');
    $totalSize = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $totalSize += filesize($file);
        }
    }
    
    return response()->json([
        'success' => true,
        'data' => [
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
        ],
    ]);
}
```

#### ب) Component جدید در Admin

```tsx
// admin/src/components/settings/storage/cache-manager.tsx

import { useState } from 'react';
import { useTranslation } from 'next-i18next';
import Card from '@/components/ui/card/card';
import Button from '@/components/ui/button/button';
import Alert from '@/components/ui/alert/alert';
import { useQuery, useMutation } from 'react-query';
import client from '@/data/client';

export default function CacheManager() {
  const { t } = useTranslation();
  const [message, setMessage] = useState<any>(null);
  
  // دریافت آمار cache
  const { data: stats, refetch } = useQuery(
    'cache-stats',
    () => client.storage.getCacheStats(),
    {
      refetchInterval: 30000, // هر 30 ثانیه
    }
  );
  
  // پاک کردن cache
  const clearCache = useMutation(
    (params: { all?: boolean; olderThan?: number }) =>
      client.storage.clearTelegramCache(params),
    {
      onSuccess: (data) => {
        setMessage({
          type: 'success',
          text: data.message,
        });
        refetch();
      },
      onError: (error: any) => {
        setMessage({
          type: 'error',
          text: error.message,
        });
      },
    }
  );
  
  return (
    <Card className="mb-5">
      <div className="mb-5">
        <h3 className="text-lg font-semibold mb-2">
          {t('form:storage-cache-management')}
        </h3>
        <p className="text-sm text-gray-500">
          {t('form:storage-cache-management-help')}
        </p>
      </div>
      
      {/* آمار */}
      {stats?.data && (
        <div className="bg-gray-100 p-4 rounded mb-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <p className="text-sm text-gray-600">
                {t('form:cache-total-files')}
              </p>
              <p className="text-2xl font-bold">
                {stats.data.total_files}
              </p>
            </div>
            <div>
              <p className="text-sm text-gray-600">
                {t('form:cache-total-size')}
              </p>
              <p className="text-2xl font-bold">
                {stats.data.total_size_formatted}
              </p>
            </div>
          </div>
        </div>
      )}
      
      {/* پیام */}
      {message && (
        <Alert
          variant={message.type}
          message={message.text}
          closeable={true}
          onClose={() => setMessage(null)}
          className="mb-4"
        />
      )}
      
      {/* دکمه‌ها */}
      <div className="flex gap-3">
        <Button
          onClick={() => clearCache.mutate({ olderThan: 7 })}
          loading={clearCache.isLoading}
          variant="outline"
        >
          {t('form:cache-clear-7-days')}
        </Button>
        
        <Button
          onClick={() => clearCache.mutate({ olderThan: 30 })}
          loading={clearCache.isLoading}
          variant="outline"
        >
          {t('form:cache-clear-30-days')}
        </Button>
        
        <Button
          onClick={() => clearCache.mutate({ all: true })}
          loading={clearCache.isLoading}
          variant="outline"
          className="border-red-500 text-red-500 hover:bg-red-50"
        >
          {t('form:cache-clear-all')}
        </Button>
      </div>
      
      <p className="text-xs text-gray-500 mt-3">
        {t('form:cache-clear-warning')}
      </p>
    </Card>
  );
}
```

#### ج) اضافه کردن به صفحه Storage Settings

```tsx
// در admin/src/components/settings/storage/index.tsx

import CacheManager from './cache-manager';

// در JSX:
<CacheManager />
```

---

## 📋 چک‌لیست پیاده‌سازی

### بک‌اند:
- [ ] Migration برای tokenهای قدیمی
- [ ] اصلاح `downloadFromLocal()` با fallback
- [ ] Route برای لینک‌های قدیمی Spatie
- [ ] تنظیمات expiration در config
- [ ] اصلاح `StorageToken::generate()` با expiration
- [ ] Command برای cleanup expired tokens
- [ ] تکمیل `downloadFromGoogleDrive()`
- [ ] تکمیل `downloadFromFTP()`
- [ ] API endpoint برای clear cache
- [ ] API endpoint برای cache stats

### فرانت‌اند:
- [ ] Component `CacheManager`
- [ ] اضافه کردن به صفحه Storage Settings
- [ ] Translations فارسی/انگلیسی
- [ ] تنظیمات expiration در UI

### تست:
- [ ] تست فایل قدیمی local (بدون token)
- [ ] تست فایل جدید local (با token)
- [ ] تست expiration
- [ ] تست clear cache از UI
- [ ] تست Google Drive
- [ ] تست FTP

---

## 🎯 اولویت‌بندی

### اولویت 1 (بحرانی):
1. ✅ Backward compatibility برای local
2. ✅ Migration برای tokenهای قدیمی

### اولویت 2 (مهم):
3. ✅ UI برای cache management
4. ✅ Expiration support

### اولویت 3 (اختیاری):
5. ⚠️ Google Drive (اگر استفاده نمی‌شود، حذف شود)
6. ⚠️ FTP (اگر استفاده نمی‌شود، حذف شود)
