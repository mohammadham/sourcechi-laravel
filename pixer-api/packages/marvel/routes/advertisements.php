<?php

use Illuminate\Support\Facades\Route;
use Marvel\Http\Controllers\AdvertisementController;

/*
|--------------------------------------------------------------------------
| Advertisement Routes
|--------------------------------------------------------------------------
*/

// Public routes (for frontend display)
Route::prefix('advertisements')->group(function () {
    // Get all active advertisements grouped by position
    Route::get('/active', [AdvertisementController::class, 'getAllActive']);
    
    // Get advertisements by specific position
    Route::get('/position/{position}', [AdvertisementController::class, 'getByPosition']);
    
    // Get position dimensions info
    Route::get('/position-dimensions', [AdvertisementController::class, 'getPositionDimensions']);
});

// Admin routes (protected)
Route::middleware(['auth:sanctum'])->prefix('advertisements')->group(function () {
    // CRUD operations
    Route::get('/', [AdvertisementController::class, 'index']);
    Route::post('/', [AdvertisementController::class, 'store']);
    Route::get('/{id}', [AdvertisementController::class, 'show']);
    Route::put('/{id}', [AdvertisementController::class, 'update']);
    Route::post('/{id}', [AdvertisementController::class, 'update']); // For form-data with files
    Route::delete('/{id}', [AdvertisementController::class, 'destroy']);
    
    // Toggle status
    Route::post('/{id}/toggle-status', [AdvertisementController::class, 'toggleStatus']);
    
    // Update order
    Route::post('/update-order', [AdvertisementController::class, 'updateOrder']);
});
