<?php

namespace Marvel\Database\Repositories;

use Marvel\Database\Models\Advertisement;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class AdvertisementRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'title' => 'like',
        'type',
        'position',
        'is_active',
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (\Exception $e) {
            // Handle exception
        }
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Advertisement::class;
    }

    /**
     * Get active advertisements by position
     */
    public function getActiveByPosition(string $position)
    {
        return $this->model
            ->active()
            ->byPosition($position)
            ->ordered()
            ->get();
    }

    /**
     * Get all active advertisements
     */
    public function getAllActive()
    {
        return $this->model
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Update order
     */
    public function updateOrder(array $items)
    {
        foreach ($items as $item) {
            $this->update([
                'order' => $item['order']
            ], $item['id']);
        }
        return true;
    }
}
