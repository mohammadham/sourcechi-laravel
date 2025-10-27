<?php


namespace Marvel\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Attachment;
use Marvel\Database\Repositories\AttachmentRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\AttachmentRequest;
use Marvel\Storage\StorageManager;
use Prettus\Validator\Exceptions\ValidatorException;


class AttachmentController extends CoreController
{
    public $repository;
    private StorageManager $storageManager;

    public function __construct(AttachmentRepository $repository)
    {
        $this->repository = $repository;
        $this->storageManager = new StorageManager();
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Attachment[]
     */
    public function index(Request $request)
    {
        return $this->repository->paginate();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AttachmentRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(AttachmentRequest $request)
    {
        $urls = [];
        
        foreach ($request->attachment as $media) {
            // Determine file type
            $mimeType = $media->getMimeType();
            $fileType = $this->determineFileType($mimeType);
            
            // Get original filename
            $originalName = $media->getClientOriginalName();
            
            // Create attachment record first
            $attachment = new Attachment();
            $attachment->file_type = $fileType;
            $attachment->save();
            
            // Upload using storage manager
            $uploadResult = $this->storageManager->upload(
                $media->getRealPath(),
                $originalName,
                $fileType
            );
            
            if (!$uploadResult['success']) {
                // Fallback to local storage with Spatie Media Library
                $attachment->addMedia($media)->toMediaCollection();
                $attachment->storage_driver = 'local';
                $attachment->save();
                
                foreach ($attachment->getMedia() as $mediaItem) {
                    $converted_url = $this->buildMediaResponse($mediaItem, $attachment);
                    $urls[] = $converted_url;
                }
                continue;
            }
            
            // Update attachment with storage info
            $attachment->storage_driver = $uploadResult['driver'];
            $attachment->storage_metadata = $uploadResult['metadata'] ?? [];
            $attachment->save();
            
            // Build response
            $converted_url = [
                'thumbnail' => $uploadResult['url'] ?? '',
                'original' => $uploadResult['url'] ?? '',
                'id' => $attachment->id,
                'storage_driver' => $uploadResult['driver'],
                'file_type' => $fileType,
            ];
            
            // For images, try to use media library for thumbnail
            if (strpos($mimeType, 'image/') !== false && $uploadResult['driver'] === 'local') {
                $attachment->addMedia($media)->toMediaCollection();
                foreach ($attachment->getMedia() as $mediaItem) {
                    $converted_url['thumbnail'] = $mediaItem->getUrl('thumbnail');
                }
            }
            
            $urls[] = $converted_url;
        }
        
        return $urls;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->repository->findOrFail($id);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AttachmentRequest $request
     * @param int $id
     * @return bool
     */
    public function update(AttachmentRequest $request, $id)
    {
        return false;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            $attachment = $this->repository->findOrFail($id);
            
            // Delete from storage driver
            if ($attachment->storage_driver && $attachment->storage_driver !== 'local') {
                $metadata = $attachment->getStorageMetadata();
                $fileId = $metadata['file_id'] ?? null;
                
                if ($fileId) {
                    $this->storageManager->delete($fileId, $attachment->storage_driver);
                }
            }
            
            // Delete attachment record
            return $attachment->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Download file from storage
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     */
    public function download(Request $request)
    {
        try {
            $id = $request->input('id');
            $attachment = $this->repository->findOrFail($id);
            
            // For local storage, use media library
            if ($attachment->storage_driver === 'local') {
                $media = $attachment->getMedia()->first();
                if ($media) {
                    return response()->download($media->getPath());
                }
            }
            
            // For other drivers, download from storage
            $metadata = $attachment->getStorageMetadata();
            $fileId = $metadata['file_id'] ?? null;
            
            if (!$fileId) {
                throw new MarvelException('File ID not found');
            }
            
            $tempPath = storage_path('app/temp/' . uniqid() . '_' . ($metadata['name'] ?? 'file'));
            
            $downloadResult = $this->storageManager->download(
                $fileId,
                $tempPath,
                $attachment->storage_driver
            );
            
            if (!$downloadResult['success']) {
                throw new MarvelException($downloadResult['message']);
            }
            
            return response()->download($tempPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Determine file type from MIME type
     */
    private function determineFileType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') !== false) {
            return 'image';
        }
        
        if (strpos($mimeType, 'video/') !== false) {
            return 'video';
        }
        
        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'document';
        }
        
        // Digital products (zip, exe, dmg, etc.)
        if (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/octet-stream',
        ])) {
            return 'digital_file';
        }
        
        return 'document';
    }

    /**
     * Build media response from Spatie Media
     */
    private function buildMediaResponse($media, $attachment): array
    {
        if (strpos($media->mime_type, 'image/') !== false) {
            return [
                'thumbnail' => $media->getUrl('thumbnail'),
                'original' => $media->getUrl(),
                'id' => $attachment->id,
                'storage_driver' => 'local',
                'file_type' => $attachment->file_type,
            ];
        }
        
        return [
            'thumbnail' => '',
            'original' => $media->getUrl(),
            'id' => $attachment->id,
            'storage_driver' => 'local',
            'file_type' => $attachment->file_type,
        ];
    }
}
