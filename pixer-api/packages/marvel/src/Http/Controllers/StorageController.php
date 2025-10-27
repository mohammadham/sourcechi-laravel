<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Storage\StorageManager;
use Marvel\Storage\Drivers\TelegramStorageDriver;
use Marvel\Storage\Drivers\GoogleDriveStorageDriver;
use Marvel\Database\Models\Settings;
use Illuminate\Support\Facades\Log;

class StorageController extends CoreController
{
    private StorageManager $storageManager;

    public function __construct()
    {
        $this->storageManager = new StorageManager();
    }

    /**
     * Get all available storage drivers
     */
    public function index(): JsonResponse
    {
        try {
            $drivers = $this->storageManager->getAvailableDrivers();
            $typeMapping = $this->storageManager->getTypeMapping();

            return response()->json([
                'success' => true,
                'drivers' => $drivers,
                'type_mapping' => $typeMapping,
            ]);
        } catch (\Exception $e) {
            Log::error('[StorageController] Failed to get drivers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve storage drivers',
            ], 500);
        }
    }

    /**
     * Test storage driver connection
     */
    public function testDriver(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'driver' => 'required|string|in:local,telegram,google_drive,ftp',
            ]);

            $result = $this->storageManager->testDriver($request->driver);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update storage configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'storage' => 'required|array',
            ]);

            $settings = Settings::getData();
            $options = $settings->options;
            $options['storage'] = $request->storage;

            $settings->options = $options;
            $settings->save();

            // Reload storage manager
            $this->storageManager->reload();

            return response()->json([
                'success' => true,
                'message' => 'Storage configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('[StorageController] Failed to update config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration',
            ], 500);
        }
    }

    /**
     * Telegram: Start phone authentication
     */
    public function telegramStartAuth(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'api_id' => 'required|string',
                'api_hash' => 'required|string',
            ]);

            $driver = new TelegramStorageDriver();
            $driver->initialize([
                'phone' => $request->phone,
                'api_id' => $request->api_id,
                'api_hash' => $request->api_hash,
            ]);

            $result = $driver->startPhoneAuth($request->phone);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Telegram: Verify OTP code
     */
    public function telegramVerifyCode(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'code' => 'required|string',
            ]);

            $driver = new TelegramStorageDriver();
            $result = $driver->verifyCode($request->phone, $request->code);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Telegram: Verify 2FA password
     */
    public function telegramVerify2FA(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'password' => 'required|string',
            ]);

            $driver = new TelegramStorageDriver();
            $result = $driver->verify2FA($request->phone, $request->password);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '2FA verification failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Telegram: Test channel
     */
    public function telegramTestChannel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'channel_id' => 'required|string',
                'api_id' => 'required|string',
                'api_hash' => 'required|string',
                'phone' => 'required|string',
            ]);

            $driver = new TelegramStorageDriver();
            $driver->initialize([
                'api_id' => $request->api_id,
                'api_hash' => $request->api_hash,
                'phone' => $request->phone,
                'channel_id' => $request->channel_id,
            ]);

            $result = $driver->testChannel($request->channel_id);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Channel test failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Telegram: Logout
     */
    public function telegramLogout(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
            ]);

            $driver = new TelegramStorageDriver();
            $driver->initialize([
                'phone' => $request->phone,
                'api_id' => '',
                'api_hash' => '',
            ]);

            $result = $driver->logout();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Google Drive: Get OAuth URL
     */
    public function googleDriveAuthUrl(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'client_id' => 'required|string',
                'client_secret' => 'required|string',
                'redirect_uri' => 'required|url',
            ]);

            $driver = new GoogleDriveStorageDriver();
            $driver->initialize([
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'redirect_uri' => $request->redirect_uri,
            ]);

            $authUrl = $driver->getAuthUrl();

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get auth URL: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Google Drive: Exchange code for tokens
     */
    public function googleDriveExchangeCode(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string',
                'client_id' => 'required|string',
                'client_secret' => 'required|string',
                'redirect_uri' => 'required|url',
            ]);

            $driver = new GoogleDriveStorageDriver();
            $driver->initialize([
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'redirect_uri' => $request->redirect_uri,
            ]);

            $result = $driver->exchangeCode($request->code);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code exchange failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}
