<?php

namespace Marvel\Storage;

use Marvel\Database\Models\TelegramSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class TelegramSessionManager
{
    /**
     * انتخاب بهترین سشن با الگوریتم Hybrid Load Balancing
     * 
     * Algorithm:
     * - محاسبه score برای هر سشن: (health_score * priority) - (active_downloads * 10)
     * - انتخاب سشن با بالاترین score
     * - اگر سشن سالم نبود، از default استفاده شود
     * 
     * @param bool $includeDefault آیا سشن پیش‌فرض را هم در نظر بگیریم؟
     * @return TelegramSession|null
     */
    public function selectBestSession(bool $includeDefault = true): ?TelegramSession
    {
        Log::info("[SessionManager] Starting session selection");
        
        // دریافت همه سشن‌های فعال، authenticated و سالم
        $sessions = TelegramSession::active()
            ->authenticated()
            ->healthy()
            ->get();
        
        if ($sessions->isEmpty()) {
            Log::warning("[SessionManager] No healthy sessions available");
            
            // اگر سشن سالمی نبود، از default استفاده کن
            if ($includeDefault) {
                return $this->getDefaultSession();
            }
            
            return null;
        }
        
        // محاسبه score برای هر سشن
        $sessionsWithScore = $sessions->map(function ($session) {
            $score = $session->calculateScore();
            
            Log::debug("[SessionManager] Session score calculated", [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'health_score' => $session->health_score,
                'priority' => $session->priority,
                'active_downloads' => $session->active_downloads,
                'calculated_score' => $score,
            ]);
            
            return [
                'session' => $session,
                'score' => $score,
            ];
        });
        
        // مرتب‌سازی بر اساس score (نزولی)
        $sorted = $sessionsWithScore->sortByDesc('score');
        
        // انتخاب بهترین سشن (بالاترین score)
        $best = $sorted->first();
        
        if ($best) {
            Log::info("[SessionManager] Best session selected", [
                'session_id' => $best['session']->id,
                'session_name' => $best['session']->name,
                'score' => $best['score'],
                'health_score' => $best['session']->health_score,
                'priority' => $best['session']->priority,
                'active_downloads' => $best['session']->active_downloads,
            ]);
            
            return $best['session'];
        }
        
        Log::warning("[SessionManager] No session could be selected");
        return null;
    }

    /**
     * دریافت سشن پیش‌فرض
     * 
     * @return TelegramSession|null
     */
    public function getDefaultSession(): ?TelegramSession
    {
        $defaultSession = TelegramSession::default()
            ->authenticated()
            ->first();
        
        if ($defaultSession) {
            if (!$defaultSession->canHandle()) {
                Log::warning("[SessionManager] Default session exists but cannot handle requests", [
                    'session_id' => $defaultSession->id,
                    'session_name' => $defaultSession->name,
                    'is_active' => $defaultSession->is_active,
                    'status' => $defaultSession->status,
                    'health_score' => $defaultSession->health_score,
                ]);
                
                return null;
            }
            
            Log::info("[SessionManager] Using default session", [
                'session_id' => $defaultSession->id,
                'session_name' => $defaultSession->name,
            ]);
            
            return $defaultSession;
        }
        
        Log::error("[SessionManager] No default session configured");
        return null;
    }

    /**
     * دریافت همه سشن‌های فعال
     * 
     * @return Collection
     */
    public function getActiveSessions(): Collection
    {
        return TelegramSession::active()->get();
    }

    /**
     * دریافت همه سشن‌های سالم
     * 
     * @return Collection
     */
    public function getHealthySessions(): Collection
    {
        return TelegramSession::active()
            ->authenticated()
            ->healthy()
            ->get();
    }

    /**
     * دریافت آمار کلی همه سشن‌ها
     * 
     * @return array
     */
    public function getStats(): array
    {
        $allSessions = TelegramSession::all();
        $activeSessions = TelegramSession::active()->get();
        $healthySessions = TelegramSession::active()->healthy()->get();
        $authenticatedSessions = TelegramSession::authenticated()->get();
        
        $totalActiveDownloads = $activeSessions->sum('active_downloads');
        $totalDownloads = $allSessions->sum('total_downloads');
        $totalUploads = $allSessions->sum('total_uploads');
        
        $stats = [
            'total_sessions' => $allSessions->count(),
            'active_sessions' => $activeSessions->count(),
            'healthy_sessions' => $healthySessions->count(),
            'authenticated_sessions' => $authenticatedSessions->count(),
            'total_active_downloads' => $totalActiveDownloads,
            'total_downloads' => $totalDownloads,
            'total_uploads' => $totalUploads,
            'has_default_session' => TelegramSession::default()->exists(),
        ];
        
        Log::info("[SessionManager] Stats retrieved", $stats);
        
        return $stats;
    }

    /**
     * چک کردن سلامت یک سشن
     * 
     * @param TelegramSession $session
     * @return array ['health_score' => int, 'error' => string|null]
     */
    public function checkSessionHealth(TelegramSession $session): array
    {
        Log::info("[SessionManager] Checking health for session", [
            'session_id' => $session->id,
            'session_name' => $session->name,
        ]);
        
        try {
            // بارگذاری TelegramStorageDriver
            $driver = new \Marvel\Storage\Drivers\TelegramStorageDriver([
                'api_id' => $session->api_id,
                'api_hash' => $session->api_hash,
                'phone' => $session->phone,
                'channel_id' => $session->channel_id,
            ]);
            
            // تست اتصال
            $testResult = $driver->testConnection();
            
            if ($testResult['success']) {
                Log::info("[SessionManager] Session is healthy", [
                    'session_id' => $session->id,
                    'session_name' => $session->name,
                ]);
                
                return [
                    'health_score' => 100,
                    'error' => null,
                ];
            } else {
                Log::warning("[SessionManager] Session has issues", [
                    'session_id' => $session->id,
                    'session_name' => $session->name,
                    'error' => $testResult['message'] ?? 'Unknown error',
                ]);
                
                return [
                    'health_score' => 50,
                    'error' => $testResult['message'] ?? 'Connection test failed',
                ];
            }
        } catch (\Exception $e) {
            Log::error("[SessionManager] Health check failed", [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return [
                'health_score' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * چک کردن سلامت همه سشن‌های فعال
     * 
     * @return array
     */
    public function checkAllSessionsHealth(): array
    {
        Log::info("[SessionManager] Starting health check for all active sessions");
        
        $activeSessions = TelegramSession::active()->get();
        $results = [];
        
        foreach ($activeSessions as $session) {
            $healthResult = $this->checkSessionHealth($session);
            
            // به‌روزرسانی وضعیت سلامت در database
            $session->updateHealthStatus(
                $healthResult['health_score'],
                $healthResult['error']
            );
            
            $results[] = [
                'session_id' => $session->id,
                'session_name' => $session->name,
                'health_score' => $healthResult['health_score'],
                'error' => $healthResult['error'],
                'auto_disabled' => $healthResult['health_score'] < 30,
            ];
        }
        
        Log::info("[SessionManager] Health check completed for all sessions", [
            'checked_sessions' => count($results),
            'results' => $results,
        ]);
        
        return $results;
    }

    /**
     * پیدا کردن سشن بر اساس ID
     * 
     * @param int $sessionId
     * @return TelegramSession|null
     */
    public function findSession(int $sessionId): ?TelegramSession
    {
        return TelegramSession::find($sessionId);
    }

    /**
     * پیدا کردن سشن بر اساس شماره تلفن
     * 
     * @param string $phone
     * @return TelegramSession|null
     */
    public function findSessionByPhone(string $phone): ?TelegramSession
    {
        return TelegramSession::where('phone', $phone)->first();
    }

    /**
     * ایجاد سشن جدید
     * 
     * @param array $data
     * @return TelegramSession
     */
    public function createSession(array $data): TelegramSession
    {
        // اگر is_default = true باشد، ابتدا همه را غیر پیش‌فرض کن
        if ($data['is_default'] ?? false) {
            TelegramSession::where('is_default', true)->update(['is_default' => false]);
        }
        
        $session = TelegramSession::create($data);
        
        Log::info("[SessionManager] New session created", [
            'session_id' => $session->id,
            'session_name' => $session->name,
            'phone' => $session->phone,
        ]);
        
        return $session;
    }

    /**
     * به‌روزرسانی سشن
     * 
     * @param TelegramSession $session
     * @param array $data
     * @return TelegramSession
     */
    public function updateSession(TelegramSession $session, array $data): TelegramSession
    {
        // اگر is_default = true باشد، ابتدا همه را غیر پیش‌فرض کن
        if (isset($data['is_default']) && $data['is_default']) {
            TelegramSession::where('is_default', true)
                ->where('id', '!=', $session->id)
                ->update(['is_default' => false]);
        }
        
        $session->update($data);
        
        Log::info("[SessionManager] Session updated", [
            'session_id' => $session->id,
            'session_name' => $session->name,
            'updated_fields' => array_keys($data),
        ]);
        
        return $session->fresh();
    }

    /**
     * حذف سشن
     * 
     * @param TelegramSession $session
     * @return bool
     */
    public function deleteSession(TelegramSession $session): bool
    {
        $sessionId = $session->id;
        $sessionName = $session->name;
        
        // حذف فایل session
        $session->deleteSessionFile();
        
        // حذف از database
        $deleted = $session->delete();
        
        if ($deleted) {
            Log::info("[SessionManager] Session deleted", [
                'session_id' => $sessionId,
                'session_name' => $sessionName,
            ]);
        }
        
        return $deleted;
    }

    /**
     * دریافت لیست سشن‌ها با فیلتر و مرتب‌سازی
     * 
     * @param array $filters
     * @param string $sortBy
     * @param string $sortOrder
     * @return Collection
     */
    public function getFilteredSessions(
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): Collection {
        $query = TelegramSession::query();
        
        // فیلترها
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['is_default'])) {
            $query->where('is_default', $filters['is_default']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['min_health_score'])) {
            $query->where('health_score', '>=', $filters['min_health_score']);
        }
        
        // مرتب‌سازی
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
}
