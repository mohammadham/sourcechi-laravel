<?php

namespace Marvel\Storage\Drivers;

use Marvel\Storage\BaseStorageDriver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalStorageDriver extends BaseStorageDriver
{
    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return 'local';
    }

    /**
     * Test connection to local storage
     */
    public function testConnection(): array
    {
        try {
            $testFile = 'test_' . time() . '.txt';
            $testContent = 'Storage test file';
            
            // Try to write
            Storage::disk('public')->put($testFile, $testContent);
            
            // Try to read
            $content = Storage::disk('public')->get($testFile);
            
            // Try to delete
            Storage::disk('public')->delete($testFile);
            
            if ($content === $testContent) {
                return $this->successResponse('Local storage is working correctly');
            }
            
            return $this->errorResponse('Local storage test failed: Content mismatch');
        } catch (\Exception $e) {
            return $this->errorResponse('Local storage test failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload file to local storage
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        try {
            if (!file_exists($filePath)) {
                return $this->errorResponse('File not found: ' . $filePath);
            }

            $directory = $this->getDirectoryByType($type);
            $uniqueName = $this->generateUniqueFileName($fileName);
            $storagePath = $directory . '/' . $uniqueName;
            
            // Upload to storage
            $uploaded = Storage::disk('public')->putFileAs(
                $directory,
                new \Illuminate\Http\File($filePath),
                $uniqueName
            );
            
            if ($uploaded) {
                return $this->successResponse('File uploaded successfully', [
                    'file_id' => $storagePath,
                    'url' => Storage::disk('public')->url($storagePath),
                    'metadata' => [
                        'size' => filesize($filePath),
                        'mime_type' => mime_content_type($filePath),
                        'original_name' => $fileName,
                    ],
                ]);
            }
            
            return $this->errorResponse('Failed to upload file');
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from local storage
     */
    public function download(string $fileId, string $localPath): array
    {
        try {
            if (!Storage::disk('public')->exists($fileId)) {
                return $this->errorResponse('File not found in storage');
            }
            
            $content = Storage::disk('public')->get($fileId);
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
        return Storage::disk('public')->url($fileId);
    }

    /**
     * Delete file from local storage
     */
    public function delete(string $fileId): array
    {
        try {
            if (Storage::disk('public')->exists($fileId)) {
                Storage::disk('public')->delete($fileId);
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

    /**
     * Generate unique file name
     */
    private function generateUniqueFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($name);
        $uniqueId = uniqid();
        
        return "{$slug}_{$uniqueId}.{$extension}";
    }
}
