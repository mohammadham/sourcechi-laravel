<?php

namespace Marvel\Storage;

interface StorageDriverInterface
{
    /**
     * Initialize driver with configuration
     *
     * @param array $config Driver configuration
     * @return void
     */
    public function initialize(array $config): void;

    /**
     * Test connection to storage
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Upload file to storage
     *
     * @param string $filePath Local file path
     * @param string $fileName Target file name
     * @param string $type File type (image, video, digital_file, etc.)
     * @return array ['success' => bool, 'file_id' => string, 'url' => string, 'metadata' => array]
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array;

    /**
     * Download file from storage
     *
     * @param string $fileId File identifier in storage
     * @param string $localPath Local destination path
     * @return array ['success' => bool, 'path' => string, 'message' => string]
     */
    public function download(string $fileId, string $localPath): array;

    /**
     * Get file URL
     *
     * @param string $fileId File identifier
     * @param int $expiresIn URL expiration time in seconds (0 = permanent)
     * @return string File URL
     */
    public function getFileUrl(string $fileId, int $expiresIn = 3600): string;

    /**
     * Delete file from storage
     *
     * @param string $fileId File identifier
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete(string $fileId): array;

    /**
     * Get driver name
     *
     * @return string
     */
    public function getDriverName(): string;

    /**
     * Check if driver is configured and ready
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
