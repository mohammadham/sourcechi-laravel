<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


class Attachment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'attachments';

    public $guarded = [];

    protected $casts = [
        'storage_metadata' => 'json',
    ];

    protected $fillable = [
        'storage_driver',
        'storage_metadata',
        'file_type',
    ];

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(368)
            ->height(232)
            ->nonQueued();
    }

    /**
     * Get storage driver name
     */
    public function getStorageDriver(): string
    {
        return $this->storage_driver ?? 'local';
    }

    /**
     * Get storage metadata
     */
    public function getStorageMetadata(): array
    {
        return $this->storage_metadata ?? [];
    }

    /**
     * Get file type
     */
    public function getFileType(): string
    {
        return $this->file_type ?? 'image';
    }
}
