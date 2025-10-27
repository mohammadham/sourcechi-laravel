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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['image', 'video', 'html'])->default('image');
            $table->enum('position', [
                'header',
                'sidebar',
                'footer',
                'between_products',
                'product_detail',
                'popup'
            ]);
            
            // Image/Video fields
            $table->text('media_url')->nullable();
            $table->string('media_type')->nullable(); // image/jpeg, video/mp4, etc.
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // HTML/JavaScript field
            $table->text('html_code')->nullable();
            
            // Link fields
            $table->text('target_url')->nullable();
            $table->boolean('open_in_new_tab')->default(true);
            
            // Status and scheduling
            $table->boolean('is_active')->default(true);
            
            // Display settings
            $table->json('display_settings')->nullable(); // Responsive breakpoints, etc.
            
            // Order/Priority
            $table->integer('order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['position', 'is_active']);
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
