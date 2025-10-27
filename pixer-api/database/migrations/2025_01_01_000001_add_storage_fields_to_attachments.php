<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Storage driver name (local, telegram, google_drive, ftp)
            $table->string('storage_driver')->default('local')->after('id');
            
            // Storage-specific metadata (JSON)
            $table->json('storage_metadata')->nullable()->after('storage_driver');
            
            // File type classification
            $table->string('file_type')->default('image')->after('storage_metadata')
                  ->comment('image, video, digital_file, document');
            
            // Add index for better performance
            $table->index('storage_driver');
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['storage_driver']);
            $table->dropIndex(['file_type']);
            $table->dropColumn(['storage_driver', 'storage_metadata', 'file_type']);
        });
    }
};
