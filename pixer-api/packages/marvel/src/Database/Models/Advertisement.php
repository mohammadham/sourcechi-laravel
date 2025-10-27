<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Marvel\Traits\TranslationTrait;

class Advertisement extends Model
{
    use SoftDeletes;
    use TranslationTrait;

    protected $table = 'advertisements';

    protected $fillable = [
        'title',
        'type',
        'position',
        'media_url',
        'media_type',
        'width',
        'height',
        'html_code',
        'target_url',
        'open_in_new_tab',
        'is_active',
        'display_settings',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'open_in_new_tab' => 'boolean',
        'display_settings' => 'json',
        'order' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * Position dimension recommendations
     */
    public static $positionDimensions = [
        'header' => [
            'recommended' => ['width' => 1200, 'height' => 150],
            'alternatives' => [
                ['width' => 970, 'height' => 90],
                ['width' => 728, 'height' => 90],
            ],
            'description' => 'بنر بالای صفحه - مناسب برای نمایش در تمام صفحات',
        ],
        'sidebar' => [
            'recommended' => ['width' => 300, 'height' => 250],
            'alternatives' => [
                ['width' => 300, 'height' => 600],
                ['width' => 160, 'height' => 600],
            ],
            'description' => 'بنر نوار کناری - نمایش در کنار محتوا',
        ],
        'footer' => [
            'recommended' => ['width' => 1200, 'height' => 100],
            'alternatives' => [
                ['width' => 970, 'height' => 90],
                ['width' => 728, 'height' => 90],
            ],
            'description' => 'بنر پایین صفحه - نمایش در انتهای صفحات',
        ],
        'between_products' => [
            'recommended' => ['width' => 728, 'height' => 90],
            'alternatives' => [
                ['width' => 970, 'height' => 90],
                ['width' => 468, 'height' => 60],
            ],
            'description' => 'بنر بین محصولات - نمایش در لیست محصولات',
        ],
        'product_detail' => [
            'recommended' => ['width' => 300, 'height' => 250],
            'alternatives' => [
                ['width' => 728, 'height' => 90],
                ['width' => 300, 'height' => 600],
            ],
            'description' => 'بنر صفحه محصول - نمایش در جزئیات محصول',
        ],
        'popup' => [
            'recommended' => ['width' => 600, 'height' => 400],
            'alternatives' => [
                ['width' => 800, 'height' => 600],
                ['width' => 400, 'height' => 300],
            ],
            'description' => 'پنجره بازشو - نمایش به صورت مودال',
        ],
    ];

    /**
     * Scope to get active advertisements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get by position
     */
    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }

    /**
     * Get advertisements ordered
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'desc');
    }

    /**
     * Get dimension info for position
     */
    public static function getDimensionsForPosition($position)
    {
        return self::$positionDimensions[$position] ?? null;
    }

    /**
     * Get all positions with dimensions
     */
    public static function getAllPositions()
    {
        return self::$positionDimensions;
    }

    /**
     * Check if advertisement is valid for display
     */
    public function canDisplay(): bool
    {
        return $this->is_active;
    }

    /**
     * Get formatted display settings
     */
    public function getDisplaySettingsAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }
        return $value;
    }

    /**
     * Get media URL (can be attachment or external URL)
     */
    public function getMediaUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If it's already a full URL, return it
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Otherwise, construct from storage path
        return url('storage/' . $value);
    }
}
