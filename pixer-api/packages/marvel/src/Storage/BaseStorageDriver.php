<?php

namespace Marvel\Storage;

use Illuminate\Support\Facades\Log;

abstract class BaseStorageDriver implements StorageDriverInterface
{
    protected array $config = [];
    protected bool $configured = false;

    /**
     * Initialize driver with configuration
     */
    public function initialize(array $config): void
    {
        $this->config = $config;
        $this->configured = $this->validateConfig();
    }

    /**
     * Validate driver configuration
     * Override in child classes for specific validation
     *
     * @return bool
     */
    protected function validateConfig(): bool
    {
        return !empty($this->config);
    }

    /**
     * Check if driver is configured and ready
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Log driver operation
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        Log::log($level, "[Storage:{$this->getDriverName()}] $message", $context);
    }

    /**
     * Get configuration value
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Build error response
     */
    protected function errorResponse(string $message, array $extra = []): array
    {
        $this->log($message, 'error', $extra);
        return array_merge([
            'success' => false,
            'message' => $message,
        ], $extra);
    }

    /**
     * Build success response
     */
    protected function successResponse(string $message, array $data = []): array
    {
        $this->log($message, 'info', $data);
        return array_merge([
            'success' => true,
            'message' => $message,
        ], $data);
    }
}
