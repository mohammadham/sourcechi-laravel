<?php

namespace Marvel\Storage;

use Marvel\Storage\Drivers\LocalStorageDriver;
use Marvel\Storage\Drivers\TelegramStorageDriver;
use Marvel\Storage\Drivers\GoogleDriveStorageDriver;
use Marvel\Storage\Drivers\FTPStorageDriver;
use Marvel\Database\Models\Settings;
use Illuminate\Support\Facades\Log;

class StorageManager
{
    private array $drivers = [];
    private array $config = [];
    private array $typeMapping = [];

    /**
     * Initialize storage manager
     */
    public function __construct()
    {
        $this->loadConfiguration();
        $this->registerDrivers();
    }

    /**
     * Load configuration from settings
     */
    private function loadConfiguration(): void
    {
        try {
            $settings = Settings::getData();
            $this->config = $settings->options['storage'] ?? [];
            $this->typeMapping = $this->config['type_mapping'] ?? [
                'image' => 'local',
                'video' => 'local',
                'digital_file' => 'local',
                'document' => 'local',
            ];
        } catch (\Exception $e) {
            Log::error('[StorageManager] Failed to load configuration: ' . $e->getMessage());
            $this->config = [];
            $this->typeMapping = [
                'image' => 'local',
                'video' => 'local',
                'digital_file' => 'local',
                'document' => 'local',
            ];
        }
    }

    /**
     * Register available drivers
     */
    private function registerDrivers(): void
    {
        // Local driver (always available)
        $this->registerDriver('local', new LocalStorageDriver());
        
        // Telegram driver
        if (!empty($this->config['drivers']['telegram']['enabled'])) {
            $this->registerDriver('telegram', new TelegramStorageDriver());
        }
        
        // Google Drive driver
        if (!empty($this->config['drivers']['google_drive']['enabled'])) {
            $this->registerDriver('google_drive', new GoogleDriveStorageDriver());
        }
        
        // FTP driver
        if (!empty($this->config['drivers']['ftp']['enabled'])) {
            $this->registerDriver('ftp', new FTPStorageDriver());
        }
    }

    /**
     * Register a driver
     */
    private function registerDriver(string $name, StorageDriverInterface $driver): void
    {
        try {
            $config = $this->config['drivers'][$name] ?? [];
            $driver->initialize($config);
            $this->drivers[$name] = $driver;
        } catch (\Exception $e) {
            Log::error("[StorageManager] Failed to register driver '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Get driver by name
     */
    public function driver(string $name = null): ?StorageDriverInterface
    {
        if ($name === null) {
            $name = $this->config['default_driver'] ?? 'local';
        }
        
        return $this->drivers[$name] ?? $this->drivers['local'] ?? null;
    }

    /**
     * Get driver for specific file type
     */
    public function driverForType(string $type): StorageDriverInterface
    {
        $driverName = $this->typeMapping[$type] ?? $this->config['default_driver'] ?? 'local';
        
        $driver = $this->drivers[$driverName] ?? null;
        
        // Fallback to local if driver not available or not configured
        if (!$driver || !$driver->isConfigured()) {
            Log::warning("[StorageManager] Driver '{$driverName}' not available, falling back to local");
            return $this->drivers['local'];
        }
        
        return $driver;
    }

    /**
     * Upload file using appropriate driver
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        $driver = $this->driverForType($type);
        
        Log::info("[StorageManager] Uploading file '{$fileName}' using driver: {$driver->getDriverName()}");
        
        $result = $driver->upload($filePath, $fileName, $type);
        
        if ($result['success']) {
            // Add driver name to result
            $result['driver'] = $driver->getDriverName();
        }
        
        return $result;
    }

    /**
     * Download file from storage
     */
    public function download(string $fileId, string $localPath, string $driverName): array
    {
        $driver = $this->driver($driverName);
        
        if (!$driver) {
            return [
                'success' => false,
                'message' => "Driver '{$driverName}' not found",
            ];
        }
        
        return $driver->download($fileId, $localPath);
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $fileId, string $driverName, int $expiresIn = 3600): string
    {
        $driver = $this->driver($driverName);
        
        if (!$driver) {
            return '';
        }
        
        return $driver->getFileUrl($fileId, $expiresIn);
    }

    /**
     * Delete file from storage
     */
    public function delete(string $fileId, string $driverName): array
    {
        $driver = $this->driver($driverName);
        
        if (!$driver) {
            return [
                'success' => false,
                'message' => "Driver '{$driverName}' not found",
            ];
        }
        
        return $driver->delete($fileId);
    }

    /**
     * Test driver connection
     */
    public function testDriver(string $driverName): array
    {
        $driver = $this->driver($driverName);
        
        if (!$driver) {
            return [
                'success' => false,
                'message' => "Driver '{$driverName}' not found",
            ];
        }
        
        if (!$driver->isConfigured()) {
            return [
                'success' => false,
                'message' => "Driver '{$driverName}' is not properly configured",
            ];
        }
        
        return $driver->testConnection();
    }

    /**
     * Get all available drivers
     */
    public function getAvailableDrivers(): array
    {
        $result = [];
        
        foreach ($this->drivers as $name => $driver) {
            $result[$name] = [
                'name' => $name,
                'configured' => $driver->isConfigured(),
                'enabled' => $this->config['drivers'][$name]['enabled'] ?? false,
            ];
        }
        
        return $result;
    }

    /**
     * Get type mapping
     */
    public function getTypeMapping(): array
    {
        return $this->typeMapping;
    }

    /**
     * Update type mapping
     */
    public function updateTypeMapping(array $mapping): void
    {
        $this->typeMapping = $mapping;
    }

    /**
     * Reload configuration
     */
    public function reload(): void
    {
        $this->drivers = [];
        $this->loadConfiguration();
        $this->registerDrivers();
    }
}
