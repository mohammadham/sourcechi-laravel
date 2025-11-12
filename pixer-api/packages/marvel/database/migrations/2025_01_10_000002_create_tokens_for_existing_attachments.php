<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ایجاد token برای attachments موجود (Backward Compatibility)
     */
    public function up(): void
    {
        // پیدا کردن تمام attachments که هنوز storage_token ندارند
        $attachments = DB::table('attachments')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('storage_tokens')
                    ->whereColumn('storage_tokens.attachment_id', 'attachments.id');
            })
            ->get();
        
        $count = 0;
        
        foreach ($attachments as $attachment) {
            $driver = $attachment->storage_driver ?? 'local';
            $metadata = json_decode($attachment->storage_metadata, true) ?? [];
            
            // تعیین prefix بر اساس driver
            $prefix = match($driver) {
                'local' => 'lc',
                'telegram' => 'tg',
                'google_drive' => 'gd',
                'ftp' => 'ft',
                default => 'lc',
            };
            
            // تولید token یکتا
            $token = $prefix . '_' . (string) Str::uuid();
            
            // ایجاد storage token
            DB::table('storage_tokens')->insert([
                'token' => $token,
                'attachment_id' => $attachment->id,
                'driver' => $driver,
                'metadata' => json_encode($metadata),
                'expires_at' => null,  // هیچوقت expire نمی‌شود
                'download_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $count++;
        }
        
        \Log::info("[Migration] Created {$count} storage tokens for existing attachments");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // در rollback، tokenهای ایجاد شده توسط این migration حذف می‌شوند
        // ولی attachments باقی می‌مانند
        
        \Log::info("[Migration] Rollback: Tokens will be automatically deleted by cascade");
    }
};
