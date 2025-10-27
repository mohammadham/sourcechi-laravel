<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Advertisement;
use Marvel\Database\Repositories\AdvertisementRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\AdvertisementRequest;
use Prettus\Validator\Exceptions\ValidatorException;

class AdvertisementController extends CoreController
{
    public $repository;

    public function __construct(AdvertisementRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the advertisements.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?? 15;
        $advertisements = $this->repository->with([])->paginate($limit);
        
        return response()->json($advertisements);
    }

    /**
     * Store a newly created advertisement.
     *
     * @param AdvertisementRequest $request
     * @return JsonResponse
     * @throws ValidatorException
     */
    public function store(AdvertisementRequest $request)
    {
        try {
            $validatedData = $request->validated();
            
            // Handle media upload if provided
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $path = $file->store('advertisements', 'public');
                $validatedData['media_url'] = $path;
                $validatedData['media_type'] = $file->getMimeType();
                
                // Get image dimensions if it's an image
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $imageSize = getimagesize($file->getRealPath());
                    if ($imageSize) {
                        $validatedData['width'] = $imageSize[0];
                        $validatedData['height'] = $imageSize[1];
                    }
                }
            }
            
            $advertisement = $this->repository->create($validatedData);
            
            return response()->json([
                'message' => 'Advertisement created successfully',
                'data' => $advertisement
            ], 201);
        } catch (\Exception $e) {
            Log::error('Advertisement creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create advertisement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified advertisement.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            $advertisement = $this->repository->findOrFail($id);
            return response()->json($advertisement);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Advertisement not found'
            ], 404);
        }
    }

    /**
     * Update the specified advertisement.
     *
     * @param AdvertisementRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(AdvertisementRequest $request, $id)
    {
        try {
            $validatedData = $request->validated();
            
            // Handle media upload if provided
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $path = $file->store('advertisements', 'public');
                $validatedData['media_url'] = $path;
                $validatedData['media_type'] = $file->getMimeType();
                
                // Get image dimensions if it's an image
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $imageSize = getimagesize($file->getRealPath());
                    if ($imageSize) {
                        $validatedData['width'] = $imageSize[0];
                        $validatedData['height'] = $imageSize[1];
                    }
                }
            }
            
            $advertisement = $this->repository->update($validatedData, $id);
            
            return response()->json([
                'message' => 'Advertisement updated successfully',
                'data' => $advertisement
            ]);
        } catch (\Exception $e) {
            Log::error('Advertisement update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update advertisement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified advertisement.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            $this->repository->delete($id);
            return response()->json([
                'message' => 'Advertisement deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete advertisement'
            ], 500);
        }
    }

    /**
     * Get advertisements by position (for frontend display)
     *
     * @param Request $request
     * @param string $position
     * @return JsonResponse
     */
    public function getByPosition(Request $request, $position)
    {
        try {
            $advertisements = $this->repository->getActiveByPosition($position);
            return response()->json($advertisements);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch advertisements',
                'data' => []
            ], 200);
        }
    }

    /**
     * Get all active advertisements (for frontend)
     *
     * @return JsonResponse
     */
    public function getAllActive()
    {
        try {
            $advertisements = $this->repository->getAllActive();
            
            // Group by position
            $grouped = $advertisements->groupBy('position');
            
            return response()->json($grouped);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch advertisements',
                'data' => []
            ], 200);
        }
    }

    /**
     * Toggle advertisement status
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $advertisement = $this->repository->findOrFail($id);
            $advertisement = $this->repository->update([
                'is_active' => !$advertisement->is_active
            ], $id);
            
            return response()->json([
                'message' => 'Advertisement status updated',
                'data' => $advertisement
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle status'
            ], 500);
        }
    }

    /**
     * Update advertisements order
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOrder(Request $request)
    {
        try {
            $items = $request->input('items', []);
            $this->repository->updateOrder($items);
            
            return response()->json([
                'message' => 'Order updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update order'
            ], 500);
        }
    }

    /**
     * Get position dimensions info
     *
     * @return JsonResponse
     */
    public function getPositionDimensions()
    {
        return response()->json(Advertisement::getAllPositions());
    }
}
