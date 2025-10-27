<?php

namespace Marvel\Storage\Drivers;

use Marvel\Storage\BaseStorageDriver;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;

class FTPStorageDriver extends BaseStorageDriver
{
    private ?Filesystem $filesystem = null;
    
    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return 'ftp';
    }

    /**
     * Validate FTP configuration
     */
    protected function validateConfig(): bool
    {
        $required = ['host', 'username', 'password'];
        
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Initialize FTP connection
     */
    private function initializeConnection(): bool
    {
        try {
            if ($this->filesystem !== null) {
                return true;
            }

            $options = FtpConnectionOptions::fromArray([
                'host' => $this->config['host'],
                'root' => $this->config['root'] ?? '/',
                'username' => $this->config['username'],
                'password' => $this->config['password'],
                'port' => (int) ($this->config['port'] ?? 21),
                'ssl' => (bool) ($this->config['ssl'] ?? false),
                'timeout' => (int) ($this->config['timeout'] ?? 30),
                'passive' => (bool) ($this->config['passive'] ?? true),
            ]);
            
            $adapter = new FtpAdapter($options);
            $this->filesystem = new Filesystem($adapter);
            
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to initialize FTP: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Test FTP connection
     */
    public function testConnection(): array
    {
        try {
            if (!$this->initializeConnection()) {
                return $this->errorResponse('Failed to connect to FTP server');
            }
            
            // Test: List directory
            $listing = $this->filesystem->listContents('/', false);
            
            // Test: Create and delete a test file
            $testFile = 'test_' . time() . '.txt';
            $testContent = 'FTP storage test';
            
            $this->filesystem->write($testFile, $testContent);
            $content = $this->filesystem->read($testFile);
            $this->filesystem->delete($testFile);
            
            if ($content !== $testContent) {
                return $this->errorResponse('FTP test failed: Content mismatch');
            }
            
            return $this->successResponse('FTP connection is working correctly', [
                'server' => [
                    'host' => $this->config['host'],
                    'port' => $this->config['port'] ?? 21,
                    'ssl' => $this->config['ssl'] ?? false,
                    'root' => $this->config['root'] ?? '/',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload file to FTP
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        try {
            if (!$this->initializeConnection()) {
                return $this->errorResponse('Failed to connect to FTP server');
            }
            
            if (!file_exists($filePath)) {
                return $this->errorResponse('File not found: ' . $filePath);
            }
            
            $directory = $this->getDirectoryByType($type);
            $remotePath = $directory . '/' . $fileName;
            
            // Create directory if not exists
            if (!$this->filesystem->directoryExists($directory)) {
                $this->filesystem->createDirectory($directory);
            }
            
            // Upload file
            $content = file_get_contents($filePath);
            $this->filesystem->write($remotePath, $content);
            
            // Build URL
            $baseUrl = $this->config['base_url'] ?? 
                       ($this->config['ssl'] ? 'https://' : 'http://') . $this->config['host'];
            $fileUrl = rtrim($baseUrl, '/') . '/' . ltrim($remotePath, '/');
            
            return $this->successResponse('File uploaded to FTP successfully', [
                'file_id' => $remotePath,
                'url' => $fileUrl,
                'metadata' => [
                    'size' => filesize($filePath),
                    'mime_type' => mime_content_type($filePath),
                    'name' => $fileName,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from FTP
     */
    public function download(string $fileId, string $localPath): array
    {
        try {
            if (!$this->initializeConnection()) {
                return $this->errorResponse('Failed to connect to FTP server');
            }
            
            if (!$this->filesystem->fileExists($fileId)) {
                return $this->errorResponse('File not found on FTP server');
            }
            
            $content = $this->filesystem->read($fileId);
            file_put_contents($localPath, $content);
            
            return $this->successResponse('File downloaded successfully', [
                'path' => $localPath,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Download failed: ' . $e->getMessage());
        }
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $fileId, int $expiresIn = 3600): string
    {
        $baseUrl = $this->config['base_url'] ?? 
                   ($this->config['ssl'] ? 'https://' : 'http://') . $this->config['host'];
        return rtrim($baseUrl, '/') . '/' . ltrim($fileId, '/');
    }

    /**
     * Delete file from FTP
     */
    public function delete(string $fileId): array
    {
        try {
            if (!$this->initializeConnection()) {
                return $this->errorResponse('Failed to connect to FTP server');
            }
            
            if ($this->filesystem->fileExists($fileId)) {
                $this->filesystem->delete($fileId);
                return $this->successResponse('File deleted successfully');
            }
            
            return $this->errorResponse('File not found');
        } catch (\Exception $e) {
            return $this->errorResponse('Delete failed: ' . $e->getMessage());
        }
    }

    /**
     * Get directory by file type
     */
    private function getDirectoryByType(string $type): string
    {
        return match($type) {
            'image' => 'images',
            'video' => 'videos',
            'digital_file' => 'digital-files',
            'document' => 'documents',
            default => 'files',
        };
    }
}
