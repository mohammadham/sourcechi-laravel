<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('telegram_sessions', function (Blueprint $table) {
            $table->id();
            
            // اطلاعات شناسایی
            $table->string('name', 100)->comment('نام سشن (مثل: Main Session, Backup 1)');
            $table->string('phone', 20)->unique()->comment('شماره تلفن');
            $table->integer('api_id')->comment('Telegram API ID');
            $table->string('api_hash', 100)->comment('Telegram API Hash');
            $table->string('channel_id', 100)->comment('کانال مشترک برای همه سشن‌ها');
            
            // تنظیمات
            $table->boolean('is_default')->default(false)->comment('سشن پیش‌فرض (فقط یکی)');
            $table->boolean('is_active')->default(true)->comment('فعال/غیرفعال');
            $table->integer('priority')->default(5)->comment('اولویت (1-10، بالاتر = مهم‌تر)');
            
            // وضعیت و سلامت
            $table->enum('status', ['authenticated', 'not_authenticated', 'error', 'disabled'])
                ->default('not_authenticated')
                ->comment('وضعیت احراز هویت');
            $table->integer('health_score')->default(100)->comment('امتیاز سلامت (0-100)');
            $table->timestamp('last_health_check')->nullable()->comment('آخرین چک سلامت');
            $table->text('health_error')->nullable()->comment('آخرین خطای health check');
            
            // آمار
            $table->integer('active_downloads')->default(0)->comment('تعداد دانلود فعال الان');
            $table->bigInteger('total_downloads')->default(0)->comment('مجموع دانلودها');
            $table->bigInteger('total_uploads')->default(0)->comment('مجموع آپلودها');
            $table->timestamp('last_used_at')->nullable()->comment('آخرین استفاده');
            
            // زمان
            $table->timestamps();
            
            // Indexes برای performance
            $table->index('is_active');
            $table->index('is_default');
            $table->index('status');
            $table->index('health_score');
            $table->index('active_downloads');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('telegram_sessions');
    }
};
