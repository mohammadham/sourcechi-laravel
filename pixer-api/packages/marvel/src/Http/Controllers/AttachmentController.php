<?php


namespace Marvel\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Attachment;
use Marvel\Database\Models\StorageToken;
use Marvel\Database\Repositories\AttachmentRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\AttachmentRequest;
use Marvel\Storage\StorageManager;
use Marvel\Storage\StorageTokenManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Prettus\Validator\Exceptions\ValidatorException;


class AttachmentController extends CoreController
{
    public $repository;
    private StorageManager $storageManager;

    public function __construct(AttachmentRepository $repository)
    {
        $this->repository = $repository;
        $this->storageManager = new StorageManager();
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Attachment[]
     */
    public function index(Request $request)
    {
        return $this->repository->paginate();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AttachmentRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(AttachmentRequest $request)
    {
        $urls = [];
        
        foreach ($request->attachment as $media) {
            // Determine file type
            $mimeType = $media->getMimeType();
            $fileType = $this->determineFileType($mimeType);
            
            // Get original filename and temp path
            $originalName = $media->getClientOriginalName();
            $tempPath = $media->getRealPath();
            
            // ⭐ Track temp file for cleanup
            $tempFileToCleanup = null;
            
            try {
                // Create attachment record first
                $attachment = new Attachment();
                $attachment->file_type = $fileType;
                $attachment->save();
                
                Log::info("[Upload] Starting upload for: {$originalName}", [
                    'temp_path' => $tempPath,
                    'file_size' => filesize($tempPath),
                    'mime_type' => $mimeType,
                ]);
                
                // Upload using storage manager
                $uploadResult = $this->storageManager->upload(
                    $tempPath,
                    $originalName,
                    $fileType
                );
                
                // ⭐ Mark temp file for cleanup if not local driver
                if ($uploadResult['success'] && $uploadResult['driver'] !== 'local') {
                    $tempFileToCleanup = $tempPath;
                }
                
                if (!$uploadResult['success']) {
                    // Fallback to local storage with Spatie Media Library
                    $attachment->addMedia($media)->toMediaCollection();
                    $attachment->storage_driver = 'local';
                    $attachment->save();
                    
                    foreach ($attachment->getMedia() as $mediaItem) {
                        $converted_url = $this->buildMediaResponse($mediaItem, $attachment);
                        $urls[] = $converted_url;
                    }
                    continue;
                }
                
                // Update attachment with storage info
                $attachment->storage_driver = $uploadResult['driver'];
                $attachment->storage_metadata = $uploadResult['metadata'] ?? [];
                $attachment->save();
                
                // ⭐ تولید token با prefix مناسب
                // Get expiration settings from config
                $expiresIn = null;
                $expirationConfig = config('shop.storage.token_expiration');
                if ($expirationConfig['enabled'] ?? false) {
                    $expiresIn = $expirationConfig['default_ttl'] ?? 86400;
                }
                
                $storageToken = StorageToken::generate(
                    $attachment,
                    $uploadResult['driver'],
                    $uploadResult['metadata'],
                    $expiresIn
                );
                
                // ⭐ ساخت URL با token
                $downloadUrl = route('storage.download', ['token' => $storageToken->token]);
                
                Log::info("[Upload] Token generated: {$storageToken->token} -> {$downloadUrl}");
                
                // Build response
                $converted_url = [
                    'thumbnail' => $downloadUrl,
                    'original' => $downloadUrl,
                    'id' => $attachment->id,
                    'storage_driver' => $uploadResult['driver'],
                    'file_type' => $fileType,
                ];
                
                // For images, try to use media library for thumbnail
                if (strpos($mimeType, 'image/') !== false && $uploadResult['driver'] === 'local') {
                    $attachment->addMedia($media)->toMediaCollection();
                    foreach ($attachment->getMedia() as $mediaItem) {
                        $converted_url['thumbnail'] = $mediaItem->getUrl('thumbnail');
                    }
                }
                
                $urls[] = $converted_url;
                
            } finally {
                // ⭐ Cleanup: پاک کردن فایل موقت برای driver های غیر local
                if ($tempFileToCleanup && file_exists($tempFileToCleanup)) {
                    $deleted = @unlink($tempFileToCleanup);
                    Log::info("[Upload Cleanup] Temp file removed", [
                        'file' => $tempFileToCleanup,
                        'success' => $deleted,
                        'file_exists_after' => file_exists($tempFileToCleanup),
                    ]);
                }
            }
        }
        
        return $urls;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->repository->findOrFail($id);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AttachmentRequest $request
     * @param int $id
     * @return bool
     */
    public function update(AttachmentRequest $request, $id)
    {
        return false;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            $attachment = $this->repository->findOrFail($id);
            
            // Delete from storage driver
            if ($attachment->storage_driver && $attachment->storage_driver !== 'local') {
                $metadata = $attachment->getStorageMetadata();
                $fileId = $metadata['file_id'] ?? null;
                
                if ($fileId) {
                    $this->storageManager->delete($fileId, $attachment->storage_driver);
                }
            }
            
            // Delete attachment record
            return $attachment->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
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
            $storageToken->recordDownload();
            
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
        
        // روش 1: استفاده از Spatie Media Library (روش اصلی)
        $media = $attachment->getFirstMedia();
        
        if ($media) {
            $path = $media->getPath();
            
            if (file_exists($path)) {
                Log::info("[Local] Serving from Media Library: {$path}");
                
                return response()->download($path, $media->file_name, [
                    'Content-Type' => $media->mime_type,
                    'Cache-Control' => 'public, max-age=31536000',  // 1 year
                    'X-Storage-Method' => 'media-library',
                ]);
            }
        }
        
        // روش 2: Fallback - سیستم قدیمی (برای backward compatibility)
        // اگر فایل در media library نبود، شاید در metadata باشد
        $metadata = $token->metadata;
        
        if (isset($metadata['path'])) {
            $oldPath = $metadata['path'];
            
            // تلاش برای پیدا کردن فایل
            if (file_exists($oldPath)) {
                Log::info("[Local] Serving from old path (fallback): {$oldPath}");
                
                $fileName = $metadata['original_name'] ?? basename($oldPath);
                $mimeType = $metadata['mime_type'] ?? mime_content_type($oldPath);
                
                return response()->download($oldPath, $fileName, [
                    'Content-Type' => $mimeType,
                    'Cache-Control' => 'public, max-age=31536000',
                    'X-Storage-Method' => 'legacy-path',
                ]);
            }
        }
        
        // روش 3: تلاش برای پیدا کردن در storage/app/public
        $publicPath = storage_path('app/public/attachments/' . $attachment->id);
        if (is_dir($publicPath)) {
            $files = glob($publicPath . '/*');
            if (!empty($files)) {
                $file = $files[0];
                Log::info("[Local] Serving from public storage (fallback): {$file}");
                
                return response()->download($file, basename($file), [
                    'Content-Type' => mime_content_type($file),
                    'Cache-Control' => 'public, max-age=31536000',
                    'X-Storage-Method' => 'public-storage',
                ]);
            }
        }
        
        // هیچ فایلی پیدا نشد
        Log::error("[Local] File not found for attachment: {$attachment->id}, token: {$token->token}");
        abort(404, 'File not found in any storage location');
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
        if (Cache::has($cacheKey) && file_exists($cachePath)) {
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
        Cache::put($cacheKey, true, now()->addDay());
        
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
        // پیاده‌سازی مشابه...
        abort(501, 'Google Drive download not implemented yet');
    }
    
    /**
     * Download from FTP
     */
    private function downloadFromFTP(StorageToken $token)
    {
        // پیاده‌سازی مشابه...
        abort(501, 'FTP download not implemented yet');
    }

    /**
     * Determine file type from MIME type
     */
    private function determineFileType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') !== false) {
            return 'image';
        }
        
        if (strpos($mimeType, 'video/') !== false) {
            return 'video';
        }
        
        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'document';
        }
        
        // Digital products (zip, exe, dmg, etc.)
        if (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/octet-stream',
        ])) {
            return 'digital_file';
        }
        
        return 'document';
    }

    /**
     * Build media response from Spatie Media
     */
    private function buildMediaResponse($media, $attachment): array
    {
        if (strpos($media->mime_type, 'image/') !== false) {
            return [
                'thumbnail' => $media->getUrl('thumbnail'),
                'original' => $media->getUrl(),
                'id' => $attachment->id,
                'storage_driver' => 'local',
                'file_type' => $attachment->file_type,
            ];
        }
        
        return [
            'thumbnail' => '',
            'original' => $media->getUrl(),
            'id' => $attachment->id,
            'storage_driver' => 'local',
            'file_type' => $attachment->file_type,
        ];
    }
}
