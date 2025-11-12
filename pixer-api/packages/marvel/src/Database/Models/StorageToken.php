<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Marvel\Storage\StorageTokenManager;

class StorageToken extends Model
{
    protected $fillable = [
        'token',
        'attachment_id',
        'driver',
        'metadata',
        'expires_at',
        'download_count',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'download_count' => 'integer',
    ];
    
    /**
     * Relationship: Attachment
     */
    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }
    
    /**
     * Generate new storage token
     * 
     * @param Attachment $attachment
     * @param string $driver Driver name (local, telegram, google_drive, ftp)
     * @param array $metadata Driver-specific metadata
     * @param int|null $expiresIn Expiration in seconds (null = never)
     * @return self
     */
    public static function generate(
        Attachment $attachment,
        string $driver,
        array $metadata,
        ?int $expiresIn = null
    ): self {
        // تولید token با prefix مناسب
        $token = StorageTokenManager::generateToken($driver);
        
        return self::create([
            'token' => $token,
            'attachment_id' => $attachment->id,
            'driver' => $driver,
            'metadata' => $metadata,
            'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
        ]);
    }
    
    /**
     * Check if token is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isPast();
    }
    
    /**
     * Check if token is valid
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    /**
     * Parse token and validate driver match
     * 
     * @return bool
     */
    public function validateDriverMatch(): bool
    {
        $parsed = StorageTokenManager::parseToken($this->token);
        
        if (!$parsed['valid']) {
            return false;
        }
        
        return $parsed['driver'] === $this->driver;
    }
    
    /**
     * Increment download count
     * 
     * @return void
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
    }
}