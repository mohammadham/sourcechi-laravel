<?php

namespace Marvel\Storage;

use Illuminate\Support\Str;

/**
 * Storage Token Manager
 * 
 * مدیریت tokenهای ذخیره‌سازی با prefix-based routing
 */
class StorageTokenManager
{
    /**
     * Driver prefixes (طول ثابت: 2 کاراکتر)
     */
    const DRIVER_PREFIXES = [
        'local'        => 'lc',
        'telegram'     => 'tg',
        'google_drive' => 'gd',
        'ftp'          => 'ft',
    ];
    
    /**
     * Reverse mapping (برای پیدا کردن driver از prefix)
     */
    const PREFIX_TO_DRIVER = [
        'lc' => 'local',
        'tg' => 'telegram',
        'gd' => 'google_drive',
        'ft' => 'ftp',
    ];
    
    /**
     * Generate token with driver prefix
     * 
     * @param string $driver Driver name (local, telegram, google_drive, ftp)
     * @return string Token in format: {prefix}_{uuid}
     * @throws \InvalidArgumentException
     */
    public static function generateToken(string $driver): string
    {
        $prefix = self::DRIVER_PREFIXES[$driver] ?? null;
        
        if (!$prefix) {
            throw new \InvalidArgumentException("Unknown driver: {$driver}");
        }
        
        $uuid = Str::uuid()->toString();
        
        return "{$prefix}_{$uuid}";
    }
    
    /**
     * Parse token to extract driver and UUID
     * 
     * @param string $token
     * @return array ['driver' => 'telegram', 'uuid' => 'xxx', 'valid' => true]
     */
    public static function parseToken(string $token): array
    {
        // Format: {prefix}_{uuid}
        // Example: tg_8f3e4a21-9b7c-4d5e-a1f3-2c8d9e6f7a4b
        if (!preg_match('/^([a-z]{2})_([a-f0-9\-]{36})$/', $token, $matches)) {
            return [
                'valid' => false,
                'error' => 'Invalid token format',
            ];
        }
        
        $prefix = $matches[1];
        $uuid = $matches[2];
        
        $driver = self::PREFIX_TO_DRIVER[$prefix] ?? null;
        
        if (!$driver) {
            return [
                'valid' => false,
                'error' => 'Unknown driver prefix: ' . $prefix,
            ];
        }
        
        return [
            'valid' => true,
            'driver' => $driver,
            'prefix' => $prefix,
            'uuid' => $uuid,
            'token' => $token,
        ];
    }
    
    /**
     * Validate token format (سریع - بدون database)
     * 
     * @param string $token
     * @return bool
     */
    public static function isValidFormat(string $token): bool
    {
        $parsed = self::parseToken($token);
        return $parsed['valid'] ?? false;
    }
    
    /**
     * Get driver from token (بدون database query)
     * 
     * @param string $token
     * @return string|null
     */
    public static function getDriverFromToken(string $token): ?string
    {
        $parsed = self::parseToken($token);
        return $parsed['driver'] ?? null;
    }
    
    /**
     * Get prefix from driver
     * 
     * @param string $driver
     * @return string|null
     */
    public static function getPrefixFromDriver(string $driver): ?string
    {
        return self::DRIVER_PREFIXES[$driver] ?? null;
    }
    
    /**
     * Get all supported drivers
     * 
     * @return array
     */
    public static function getSupportedDrivers(): array
    {
        return array_keys(self::DRIVER_PREFIXES);
    }
}