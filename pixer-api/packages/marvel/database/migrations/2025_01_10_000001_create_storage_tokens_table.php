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
        Schema::create('storage_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();  // {prefix}_{uuid}
            $table->unsignedBigInteger('attachment_id');
            $table->string('driver', 50);  // local, telegram, google_drive, ftp
            $table->json('metadata')->nullable();  // Driver-specific data
            $table->timestamp('expires_at')->nullable();  // null = never expires
            $table->integer('download_count')->default(0);
            $table->timestamps();
            
            // Foreign key
            $table->foreign('attachment_id')
                ->references('id')
                ->on('attachments')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('token');
            $table->index(['driver', 'attachment_id']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_tokens');
    }
};