<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // JSON array of language codes: ["fa", "en", "de", "ar"]
            $table->json('available_languages')->nullable()->after('language');
            
            // If true, product is available in ALL languages (current and future)
            $table->boolean('all_languages')->default(false)->after('available_languages');
            
            // Index for better performance
            $table->index('all_languages');
        });

        // Data migration: Update existing products
        // Set available_languages to current language for backward compatibility
        DB::statement("
            UPDATE products 
            SET available_languages = JSON_ARRAY(language),
                all_languages = false
            WHERE available_languages IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['all_languages']);
            $table->dropColumn(['available_languages', 'all_languages']);
        });
    }
};
