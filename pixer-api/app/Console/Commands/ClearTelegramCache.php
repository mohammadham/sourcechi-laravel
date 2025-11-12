<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearTelegramCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:clear-cache 
                          {--older-than=7 : Days older than}
                          {--all : Clear all cached files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Telegram cached files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = storage_path('app/cache/telegram');
        
        if (!is_dir($path)) {
            $this->info('No cache directory found');
            return 0;
        }
        
        if ($this->option('all')) {
            // Delete all
            $count = 0;
            $size = 0;
            
            foreach (glob($path . '/*') as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                    unlink($file);
                    
                    // Clear cache entry
                    $tokenId = basename($file);
                    Cache::forget("telegram_file_{$tokenId}");
                    
                    $count++;
                }
            }
            
            $this->info("Deleted {$count} cached files (" . $this->formatBytes($size) . ")");
            return 0;
        }
        
        // Delete old files
        $olderThan = (int) $this->option('older-than');
        $threshold = now()->subDays($olderThan);
        
        $count = 0;
        $size = 0;
        
        foreach (glob($path . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $threshold->timestamp) {
                $fileSize = filesize($file);
                $size += $fileSize;
                
                unlink($file);
                
                // Clear cache entry
                $tokenId = basename($file);
                Cache::forget("telegram_file_{$tokenId}");
                
                $count++;
            }
        }
        
        $this->info("Deleted {$count} cached files older than {$olderThan} days (" . $this->formatBytes($size) . ")");
        return 0;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
