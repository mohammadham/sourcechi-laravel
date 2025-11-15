<?php

use Illuminate\Support\Facades\Route;
use Marvel\Http\Controllers\StorageController;
use Marvel\Http\Controllers\AttachmentController;
use Marvel\Http\Controllers\TelegramSessionController;

/*
|--------------------------------------------------------------------------
| Storage API Routes
|--------------------------------------------------------------------------
|
| Routes for managing storage drivers (Local, Telegram, Google Drive, FTP)
|
*/

Route::prefix('storage')->group(function () {
    
    // ⭐ Download file by token (Public - تک endpoint برای همه drivers)
    Route::get('/download/{token}', [AttachmentController::class, 'download'])
        ->name('storage.download')
        ->where('token', '[a-z]{2}_[a-f0-9\-]{36}');
    
    // Get all drivers info
    Route::get('/', [StorageController::class, 'index']);
    
    // Test driver connection
    Route::post('/test', [StorageController::class, 'testDriver']);
    
    // Update storage configuration
    Route::post('/config', [StorageController::class, 'updateConfig'])
        ->middleware(['auth:sanctum', 'role:super_admin']);
    
    // Telegram Authentication Routes
    Route::prefix('telegram')->group(function () {
        Route::post('/auth/check', [StorageController::class, 'telegramCheckAuth']);
        Route::post('/auth/start', [StorageController::class, 'telegramStartAuth']);
        Route::post('/auth/verify', [StorageController::class, 'telegramVerifyCode']);
        Route::post('/auth/2fa', [StorageController::class, 'telegramVerify2FA']);
        Route::post('/test-channel', [StorageController::class, 'telegramTestChannel']);
        Route::post('/logout', [StorageController::class, 'telegramLogout']);
    });
    
    // Cache Management Routes
    Route::prefix('cache')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/stats', [StorageController::class, 'getCacheStats']);
        Route::post('/clear-telegram', [StorageController::class, 'clearTelegramCache']);
    });
    
    // Google Drive OAuth Routes
    Route::prefix('google-drive')->group(function () {
        Route::post('/auth-url', [StorageController::class, 'googleDriveAuthUrl']);
        Route::post('/exchange-code', [StorageController::class, 'googleDriveExchangeCode']);
    });
});

/*
|--------------------------------------------------------------------------
| Telegram Multi-Session Management Routes
|--------------------------------------------------------------------------
|
| Routes for managing multiple Telegram sessions with load balancing
|
*/

Route::prefix('telegram-sessions')->middleware(['auth:sanctum'])->group(function () {
    
    // لیست و آمار
    Route::get('/', [TelegramSessionController::class, 'index']);
    Route::get('/stats', [TelegramSessionController::class, 'getStats']);
    
    // CRUD عملیات
    Route::post('/', [TelegramSessionController::class, 'store']);
    Route::get('/{id}', [TelegramSessionController::class, 'show']);
    Route::put('/{id}', [TelegramSessionController::class, 'update']);
    Route::delete('/{id}', [TelegramSessionController::class, 'destroy']);
    
    // Login Flow
    Route::post('/{id}/login/start', [TelegramSessionController::class, 'startLogin']);
    Route::post('/{id}/login/verify', [TelegramSessionController::class, 'verifyCode']);
    Route::post('/{id}/login/2fa', [TelegramSessionController::class, 'verify2FA']);
    
    // مدیریت سشن
    Route::post('/{id}/test', [TelegramSessionController::class, 'testHealth']);
    Route::post('/{id}/set-default', [TelegramSessionController::class, 'setDefault']);
    Route::post('/{id}/toggle-active', [TelegramSessionController::class, 'toggleActive']);
    Route::post('/{id}/logout', [TelegramSessionController::class, 'logout']);
    
    // Health Check
    Route::post('/check-health', [TelegramSessionController::class, 'checkAllHealth']);
});
