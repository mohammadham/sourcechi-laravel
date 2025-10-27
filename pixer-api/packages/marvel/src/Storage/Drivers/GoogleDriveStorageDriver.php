<?php

namespace Marvel\Storage\Drivers;

use Marvel\Storage\BaseStorageDriver;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Cache;

class GoogleDriveStorageDriver extends BaseStorageDriver
{
    private ?Client $client = null;
    private ?Drive $service = null;
    
    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return 'google_drive';
    }

    /**
     * Validate Google Drive configuration
     */
    protected function validateConfig(): bool
    {
        $required = ['client_id', 'client_secret', 'refresh_token'];
        
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Initialize Google Drive client
     */
    private function initializeClient(): bool
    {
        try {
            if ($this->client !== null) {
                return true;
            }

            $this->client = new Client();
            $this->client->setClientId($this->config['client_id']);
            $this->client->setClientSecret($this->config['client_secret']);
            $this->client->setAccessType('offline');
            $this->client->setApprovalPrompt('force');
            
            // Set refresh token
            if (!empty($this->config['refresh_token'])) {
                $this->client->refreshToken($this->config['refresh_token']);
            }
            
            $this->service = new Drive($this->client);
            
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to initialize Google Drive: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(): string
    {
        $client = new Client();
        $client->setClientId($this->config['client_id']);
        $client->setClientSecret($this->config['client_secret']);
        $client->setRedirectUri($this->config['redirect_uri'] ?? url('/admin/settings/storage/google-drive/callback'));
        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        
        return $client->createAuthUrl();
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCode(string $code): array
    {
        try {
            $client = new Client();
            $client->setClientId($this->config['client_id']);
            $client->setClientSecret($this->config['client_secret']);
            $client->setRedirectUri($this->config['redirect_uri'] ?? url('/admin/settings/storage/google-drive/callback'));
            
            $token = $client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                return $this->errorResponse('Failed to get access token: ' . $token['error']);
            }
            
            return $this->successResponse('Successfully authenticated', [
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in' => $token['expires_in'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Code exchange failed: ' . $e->getMessage());
        }
    }

    /**
     * Test connection to Google Drive
     */
    public function testConnection(): array
    {
        try {
            if (!$this->initializeClient()) {
                return $this->errorResponse('Failed to initialize Google Drive client');
            }
            
            // Test: Get user info
            $about = $this->service->about->get(['fields' => 'user,storageQuota']);
            
            if (!$about) {
                return $this->errorResponse('Failed to get Drive information');
            }
            
            $user = $about->getUser();
            $quota = $about->getStorageQuota();
            
            // Test: Create and delete a test file
            $testContent = 'Google Drive storage test';
            $testFile = new DriveFile();
            $testFile->setName('test_' . time() . '.txt');
            $testFile->setParents([$this->config['folder_id'] ?? 'root']);
            
            $createdFile = $this->service->files->create($testFile, [
                'data' => $testContent,
                'mimeType' => 'text/plain',
                'uploadType' => 'multipart',
            ]);
            
            // Delete test file
            $this->service->files->delete($createdFile->getId());
            
            return $this->successResponse('Google Drive connection is working correctly', [
                'user' => [
                    'email' => $user->getEmailAddress(),
                    'name' => $user->getDisplayName(),
                ],
                'storage' => [
                    'limit' => $quota->getLimit() ? round($quota->getLimit() / (1024 * 1024 * 1024), 2) . ' GB' : 'Unlimited',
                    'usage' => round($quota->getUsage() / (1024 * 1024 * 1024), 2) . ' GB',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload file to Google Drive
     */
    public function upload(string $filePath, string $fileName, string $type = 'image'): array
    {
        try {
            if (!$this->initializeClient()) {
                return $this->errorResponse('Failed to initialize Google Drive client');
            }
            
            if (!file_exists($filePath)) {
                return $this->errorResponse('File not found: ' . $filePath);
            }
            
            $folderId = $this->config['folder_id'] ?? 'root';
            $mimeType = mime_content_type($filePath);
            
            $fileMetadata = new DriveFile();
            $fileMetadata->setName($fileName);
            $fileMetadata->setParents([$folderId]);
            
            $content = file_get_contents($filePath);
            
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,mimeType,size,webViewLink,webContentLink',
            ]);
            
            // Make file accessible
            $permission = new \Google\Service\Drive\Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $this->service->permissions->create($file->getId(), $permission);
            
            return $this->successResponse('File uploaded to Google Drive successfully', [
                'file_id' => $file->getId(),
                'url' => $file->getWebViewLink(),
                'download_url' => $file->getWebContentLink(),
                'metadata' => [
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'name' => $file->getName(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from Google Drive
     */
    public function download(string $fileId, string $localPath): array
    {
        try {
            if (!$this->initializeClient()) {
                return $this->errorResponse('Failed to initialize Google Drive client');
            }
            
            $response = $this->service->files->get($fileId, ['alt' => 'media']);
            $content = $response->getBody()->getContents();
            
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
        try {
            if (!$this->initializeClient()) {
                return '';
            }
            
            $file = $this->service->files->get($fileId, ['fields' => 'webContentLink,webViewLink']);
            return $file->getWebContentLink() ?? $file->getWebViewLink();
        } catch (\Exception $e) {
            $this->log('Failed to get file URL: ' . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Delete file from Google Drive
     */
    public function delete(string $fileId): array
    {
        try {
            if (!$this->initializeClient()) {
                return $this->errorResponse('Failed to initialize Google Drive client');
            }
            
            $this->service->files->delete($fileId);
            
            return $this->successResponse('File deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Delete failed: ' . $e->getMessage());
        }
    }
}
