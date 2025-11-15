<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Storage\TelegramSessionManager;
use Marvel\Storage\Drivers\TelegramStorageDriver;
use Marvel\Database\Models\TelegramSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller برای مدیریت Multi-Session تلگرام
 * 
 * این Controller تمام عملیات CRUD و مدیریتی برای سشن‌های تلگرام را فراهم می‌کند
 */
class TelegramSessionController extends CoreController
{
    private TelegramSessionManager $sessionManager;

    public function __construct()
    {
        $this->sessionManager = new TelegramSessionManager();
    }

    /**
     * لیست همه سشن‌ها با آمار
     * GET /api/telegram-sessions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // دریافت فیلترها از query parameters
            $filters = [];
            
            if ($request->has('is_active')) {
                $filters['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }
            
            if ($request->has('is_default')) {
                $filters['is_default'] = filter_var($request->is_default, FILTER_VALIDATE_BOOLEAN);
            }
            
            if ($request->has('status')) {
                $filters['status'] = $request->status;
            }
            
            if ($request->has('min_health_score')) {
                $filters['min_health_score'] = (int) $request->min_health_score;
            }
            
            // مرتب‌سازی
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // دریافت سشن‌ها
            $sessions = $this->sessionManager->getFilteredSessions($filters, $sortBy, $sortOrder);
            
            // دریافت آمار کلی
            $stats = $this->sessionManager->getStats();
            
            return response()->json([
                'success' => true,
                'data' => $sessions->map(fn($session) => $session->toApiArray()),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to get sessions list', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست سشن‌ها: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * افزودن سشن جدید
     * POST /api/telegram-sessions
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:20|unique:telegram_sessions,phone',
                'api_id' => 'required|integer',
                'api_hash' => 'required|string|max:100',
                'channel_id' => 'required|string|max:100',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'priority' => 'integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در اعتبارسنجی داده‌ها',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // ایجاد سشن جدید
            $session = $this->sessionManager->createSession($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'سشن با موفقیت ایجاد شد',
                'data' => $session->toApiArray(),
            ], 201);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to create session', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * نمایش جزئیات یک سشن
     * GET /api/telegram-sessions/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $session->toApiArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to get session details', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت جزئیات سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ویرایش سشن
     * PUT /api/telegram-sessions/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:100',
                'phone' => 'string|max:20|unique:telegram_sessions,phone,' . $id,
                'api_id' => 'integer',
                'api_hash' => 'string|max:100',
                'channel_id' => 'string|max:100',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'priority' => 'integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در اعتبارسنجی داده‌ها',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // به‌روزرسانی سشن
            $updatedSession = $this->sessionManager->updateSession($session, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'سشن با موفقیت به‌روزرسانی شد',
                'data' => $updatedSession->toApiArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to update session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در به‌روزرسانی سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف سشن
     * DELETE /api/telegram-sessions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // اگر سشن پیش‌فرض است، نمی‌توان حذف کرد
            if ($session->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'نمی‌توان سشن پیش‌فرض را حذف کرد. ابتدا سشن دیگری را به عنوان پیش‌فرض تنظیم کنید.',
                ], 400);
            }

            $this->sessionManager->deleteSession($session);

            return response()->json([
                'success' => true,
                'message' => 'سشن با موفقیت حذف شد',
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to delete session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * شروع فرآیند لاگین (ارسال کد)
     * POST /api/telegram-sessions/{id}/login/start
     */
    public function startLogin(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // Initialize Telegram driver
            $driver = new TelegramStorageDriver([
                'api_id' => $session->api_id,
                'api_hash' => $session->api_hash,
                'phone' => $session->phone,
                'channel_id' => $session->channel_id,
            ]);

            // شروع فرآیند لاگین
            $result = $driver->startPhoneAuth();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to start login', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در شروع فرآیند لاگین: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تایید کد لاگین
     * POST /api/telegram-sessions/{id}/login/verify
     */
    public function verifyCode(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'code' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'کد ضروری است',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Initialize Telegram driver
            $driver = new TelegramStorageDriver([
                'api_id' => $session->api_id,
                'api_hash' => $session->api_hash,
                'phone' => $session->phone,
                'channel_id' => $session->channel_id,
            ]);

            // تایید کد
            $result = $driver->verifyCode($request->code);

            // اگر لاگین موفق بود، وضعیت را به‌روزرسانی کن
            if ($result['success']) {
                $session->update([
                    'status' => 'authenticated',
                    'health_score' => 100,
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to verify code', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تایید کد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تایید 2FA
     * POST /api/telegram-sessions/{id}/login/2fa
     */
    public function verify2FA(Request $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز عبور ضروری است',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Initialize Telegram driver
            $driver = new TelegramStorageDriver([
                'api_id' => $session->api_id,
                'api_hash' => $session->api_hash,
                'phone' => $session->phone,
                'channel_id' => $session->channel_id,
            ]);

            // تایید 2FA
            $result = $driver->verify2FA($request->password);

            // اگر لاگین موفق بود، وضعیت را به‌روزرسانی کن
            if ($result['success']) {
                $session->update([
                    'status' => 'authenticated',
                    'health_score' => 100,
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to verify 2FA', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تایید رمز دو مرحله‌ای: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تست سلامت یک سشن
     * POST /api/telegram-sessions/{id}/test
     */
    public function testHealth(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // چک سلامت
            $healthResult = $this->sessionManager->checkSessionHealth($session);

            // به‌روزرسانی database
            $session->updateHealthStatus($healthResult['health_score'], $healthResult['error']);

            return response()->json([
                'success' => true,
                'message' => 'تست سلامت انجام شد',
                'data' => [
                    'session_id' => $session->id,
                    'session_name' => $session->name,
                    'health_score' => $healthResult['health_score'],
                    'error' => $healthResult['error'],
                    'is_healthy' => $healthResult['health_score'] >= 30,
                    'auto_disabled' => $healthResult['health_score'] < 30,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to test session health', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست سلامت: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تنظیم سشن به عنوان پیش‌فرض
     * POST /api/telegram-sessions/{id}/set-default
     */
    public function setDefault(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // بررسی اینکه سشن می‌تواند پیش‌فرض شود (باید authenticated و سالم باشد)
            if ($session->status !== 'authenticated') {
                return response()->json([
                    'success' => false,
                    'message' => 'فقط سشن‌های لاگین شده می‌توانند پیش‌فرض شوند',
                ], 400);
            }

            if (!$session->isHealthy()) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن سالم نیست و نمی‌تواند پیش‌فرض شود',
                ], 400);
            }

            // تنظیم به عنوان پیش‌فرض
            $session->setAsDefault();

            return response()->json([
                'success' => true,
                'message' => 'سشن به عنوان پیش‌فرض تنظیم شد',
                'data' => $session->fresh()->toApiArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to set default session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تنظیم سشن پیش‌فرض: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * فعال/غیرفعال کردن سشن
     * POST /api/telegram-sessions/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // اگر سشن پیش‌فرض است و قصد غیرفعال کردن آن را داریم
            if ($session->is_default && $session->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'نمی‌توان سشن پیش‌فرض را غیرفعال کرد. ابتدا سشن دیگری را به عنوان پیش‌فرض تنظیم کنید.',
                ], 400);
            }

            // تغییر وضعیت
            $newStatus = $session->toggleActive();

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'سشن فعال شد' : 'سشن غیرفعال شد',
                'data' => $session->fresh()->toApiArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to toggle session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در تغییر وضعیت سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * خروج از سشن (Logout)
     * POST /api/telegram-sessions/{id}/logout
     */
    public function logout(int $id): JsonResponse
    {
        try {
            $session = $this->sessionManager->findSession($id);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'سشن مورد نظر یافت نشد',
                ], 404);
            }

            // حذف فایل session
            $session->deleteSessionFile();

            // به‌روزرسانی وضعیت
            $session->update([
                'status' => 'not_authenticated',
                'is_active' => false,
            ]);

            // اگر سشن پیش‌فرض بود، warning بده
            if ($session->is_default) {
                return response()->json([
                    'success' => true,
                    'message' => 'خروج انجام شد. توجه: این سشن پیش‌فرض بود. لطفا سشن دیگری را پیش‌فرض کنید.',
                    'warning' => 'default_session_logged_out',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'خروج از سشن انجام شد',
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to logout session', [
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در خروج از سشن: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * دریافت آمار کلی همه سشن‌ها
     * GET /api/telegram-sessions/stats
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->sessionManager->getStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to get stats', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت آمار: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * چک کردن سلامت همه سشن‌ها
     * POST /api/telegram-sessions/check-health
     */
    public function checkAllHealth(): JsonResponse
    {
        try {
            $results = $this->sessionManager->checkAllSessionsHealth();

            return response()->json([
                'success' => true,
                'message' => 'چک سلامت همه سشن‌ها انجام شد',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('[TelegramSessionController] Failed to check all sessions health', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در چک سلامت: ' . $e->getMessage(),
            ], 500);
        }
    }
}
