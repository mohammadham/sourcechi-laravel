<?php

use Illuminate\Support\Facades\Route;
use Marvel\Http\Controllers\StorageController;
use Marvel\Http\Controllers\AttachmentController;

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
    Route::prefix('cache')->middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        Route::get('/stats', [StorageController::class, 'getCacheStats']);
        Route::post('/clear-telegram', [StorageController::class, 'clearTelegramCache']);
    });
    
    // Google Drive OAuth Routes
    Route::prefix('google-drive')->group(function () {
        Route::post('/auth-url', [StorageController::class, 'googleDriveAuthUrl']);
        Route::post('/exchange-code', [StorageController::class, 'googleDriveExchangeCode']);
    });
});
