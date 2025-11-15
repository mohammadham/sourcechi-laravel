<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TelegramSession extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'api_id',
        'api_hash',
        'channel_id',
        'is_default',
        'is_active',
        'priority',
        'status',
        'health_score',
        'last_health_check',
        'health_error',
        'active_downloads',
        'total_downloads',
        'total_uploads',
        'last_used_at',
    ];

    protected $casts = [
        'api_id' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'health_score' => 'integer',
        'active_downloads' => 'integer',
        'total_downloads' => 'integer',
        'total_uploads' => 'integer',
        'last_health_check' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'priority' => 5,
        'status' => 'not_authenticated',
        'health_score' => 100,
        'active_downloads' => 0,
        'total_downloads' => 0,
        'total_uploads' => 0,
    ];

    /**
     * Scopes
     */

    /**
     * Scope برای سشن‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope برای سشن‌های سالم (health_score >= 30)
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_score', '>=', 30);
    }

    /**
     * Scope برای سشن پیش‌فرض
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope برای سشن‌های authenticated
     */
    public function scopeAuthenticated($query)
    {
        return $query->where('status', 'authenticated');
    }

    /**
     * Helper Methods
     */

    /**
     * بررسی سلامت سشن
     */
    public function isHealthy(): bool
    {
        return $this->health_score >= 30;
    }

    /**
     * بررسی اینکه آیا سشن می‌تواند درخواست را handle کند
     */
    public function canHandle(): bool
    {
        return $this->is_active && 
               $this->status === 'authenticated' && 
               $this->isHealthy();
    }

    /**
     * محاسبه امتیاز برای Load Balancing
     * 
     * Formula: (health_score * priority) - (active_downloads * 10)
     */
    public function calculateScore(): int
    {
        return ($this->health_score * $this->priority) - ($this->active_downloads * 10);
    }

    /**
     * افزایش تعداد دانلود فعال
     */
    public function incrementActiveDownloads(): void
    {
        $this->increment('active_downloads');
        $this->update(['last_used_at' => now()]);
        
        Log::info("[TelegramSession] Active downloads incremented", [
            'session_id' => $this->id,
            'session_name' => $this->name,
            'active_downloads' => $this->active_downloads + 1,
        ]);
    }

    /**
     * کاهش تعداد دانلود فعال
     */
    public function decrementActiveDownloads(): void
    {
        if ($this->active_downloads > 0) {
            $this->decrement('active_downloads');
            
            Log::info("[TelegramSession] Active downloads decremented", [
                'session_id' => $this->id,
                'session_name' => $this->name,
                'active_downloads' => $this->active_downloads - 1,
            ]);
        }
    }

    /**
     * افزایش مجموع دانلودها
     */
    public function incrementTotalDownloads(): void
    {
        $this->increment('total_downloads');
        
        Log::info("[TelegramSession] Total downloads incremented", [
            'session_id' => $this->id,
            'session_name' => $this->name,
            'total_downloads' => $this->total_downloads + 1,
        ]);
    }

    /**
     * افزایش مجموع آپلودها
     */
    public function incrementTotalUploads(): void
    {
        $this->increment('total_uploads');
        
        Log::info("[TelegramSession] Total uploads incremented", [
            'session_id' => $this->id,
            'session_name' => $this->name,
            'total_uploads' => $this->total_uploads + 1,
        ]);
    }

    /**
     * به‌روزرسانی وضعیت سلامت
     */
    public function updateHealthStatus(int $healthScore, ?string $error = null): void
    {
        $this->update([
            'health_score' => $healthScore,
            'last_health_check' => now(),
            'health_error' => $error,
        ]);

        // اگر health_score کمتر از 30 شد، سشن را غیرفعال کن
        if ($healthScore < 30 && $this->is_active) {
            $this->update([
                'is_active' => false,
                'status' => 'error',
            ]);
            
            Log::warning("[TelegramSession] Session auto-disabled due to low health score", [
                'session_id' => $this->id,
                'session_name' => $this->name,
                'health_score' => $healthScore,
                'error' => $error,
            ]);
        }
        
        Log::info("[TelegramSession] Health status updated", [
            'session_id' => $this->id,
            'session_name' => $this->name,
            'health_score' => $healthScore,
            'error' => $error,
        ]);
    }

    /**
     * تنظیم به عنوان سشن پیش‌فرض
     */
    public function setAsDefault(): bool
    {
        // ابتدا همه سشن‌ها را غیر پیش‌فرض کن
        static::where('is_default', true)->update(['is_default' => false]);
        
        // این سشن را پیش‌فرض کن
        $this->update(['is_default' => true]);
        
        Log::info("[TelegramSession] Session set as default", [
            'session_id' => $this->id,
            'session_name' => $this->name,
        ]);
        
        return true;
    }

    /**
     * تغییر وضعیت فعال/غیرفعال
     */
    public function toggleActive(): bool
    {
        $newStatus = !$this->is_active;
        $this->update(['is_active' => $newStatus]);
        
        Log::info("[TelegramSession] Session active status toggled", [
            'session_id' => $this->id,
            'session_name' => $this->name,
            'is_active' => $newStatus,
        ]);
        
        return $newStatus;
    }

    /**
     * دریافت مسیر فایل session
     */
    public function getSessionPath(): string
    {
        return storage_path('app/telegram/session_' . md5($this->phone) . '.madeline');
    }

    /**
     * بررسی اینکه آیا فایل session وجود دارد
     */
    public function hasSessionFile(): bool
    {
        return file_exists($this->getSessionPath());
    }

    /**
     * حذف فایل session
     */
    public function deleteSessionFile(): bool
    {
        $sessionPath = $this->getSessionPath();
        
        try {
            if (file_exists($sessionPath)) {
                unlink($sessionPath);
                
                Log::info("[TelegramSession] Session file deleted", [
                    'session_id' => $this->id,
                    'session_name' => $this->name,
                    'path' => $sessionPath,
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("[TelegramSession] Failed to delete session file", [
                'session_id' => $this->id,
                'session_name' => $this->name,
                'path' => $sessionPath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * تبدیل به آرایه برای API response
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'channel_id' => $this->channel_id,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'status' => $this->status,
            'health_score' => $this->health_score,
            'last_health_check' => $this->last_health_check?->toIso8601String(),
            'health_error' => $this->health_error,
            'active_downloads' => $this->active_downloads,
            'total_downloads' => $this->total_downloads,
            'total_uploads' => $this->total_uploads,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'can_handle' => $this->canHandle(),
            'score' => $this->calculateScore(),
            'has_session_file' => $this->hasSessionFile(),
        ];
    }

    /**
     * Events
     */

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // قبل از حذف، فایل session را هم حذف کن
        static::deleting(function ($session) {
            $session->deleteSessionFile();
        });
    }
}
