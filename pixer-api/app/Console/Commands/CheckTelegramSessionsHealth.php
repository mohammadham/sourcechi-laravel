<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Storage\TelegramSessionManager;
use Illuminate\Support\Facades\Log;

/**
 * Artisan Command برای چک کردن سلامت همه سشن‌های تلگرام
 * 
 * این Command هر ساعت به صورت خودکار اجرا می‌شود و:
 * - سلامت همه سشن‌های فعال را چک می‌کند
 * - health_score را به‌روزرسانی می‌کند
 * - سشن‌های خراب (health_score < 30) را به صورت خودکار غیرفعال می‌کند
 * 
 * Usage:
 * php artisan telegram:check-sessions-health
 */
class CheckTelegramSessionsHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:check-sessions-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'چک کردن سلامت همه سشن‌های تلگرام و غیرفعال‌سازی خودکار سشن‌های خراب';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🏥 شروع چک سلامت سشن‌های تلگرام...');
        $this->newLine();
        
        try {
            $sessionManager = new TelegramSessionManager();
            
            // دریافت سشن‌های فعال
            $activeSessions = $sessionManager->getActiveSessions();
            
            if ($activeSessions->isEmpty()) {
                $this->warn('⚠️  هیچ سشن فعالی برای چک کردن وجود ندارد.');
                return Command::SUCCESS;
            }
            
            $this->info("📊 تعداد سشن‌های فعال: {$activeSessions->count()}");
            $this->newLine();
            
            // چک کردن سلامت همه سشن‌ها
            $results = $sessionManager->checkAllSessionsHealth();
            
            // نمایش نتایج
            $this->displayResults($results);
            
            // آمار نهایی
            $this->displaySummary($results);
            
            $this->newLine();
            $this->info('✅ چک سلامت با موفقیت انجام شد.');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ خطا در چک سلامت: ' . $e->getMessage());
            
            Log::error('[CheckTelegramSessionsHealth] Command failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * نمایش نتایج چک سلامت
     */
    private function displayResults(array $results): void
    {
        $this->info('📋 نتایج چک سلامت:');
        $this->newLine();
        
        foreach ($results as $result) {
            $status = $this->getHealthStatusEmoji($result['health_score']);
            $autoDisabled = $result['auto_disabled'] ? ' 🔴 [AUTO DISABLED]' : '';
            
            $this->line(sprintf(
                '%s %s (ID: %d)',
                $status,
                $result['session_name'],
                $result['session_id']
            ));
            
            $this->line(sprintf(
                '   Health Score: %d/100%s',
                $result['health_score'],
                $autoDisabled
            ));
            
            if ($result['error']) {
                $this->line('   ❌ Error: ' . $result['error']);
            }
            
            $this->newLine();
        }
    }

    /**
     * نمایش خلاصه آمار
     */
    private function displaySummary(array $results): void
    {
        $total = count($results);
        $healthy = collect($results)->filter(fn($r) => $r['health_score'] >= 30)->count();
        $unhealthy = $total - $healthy;
        $autoDisabled = collect($results)->filter(fn($r) => $r['auto_disabled'])->count();
        
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 خلاصه:');
        $this->line("   • مجموع سشن‌های چک شده: {$total}");
        $this->line("   • سشن‌های سالم: {$healthy} ✅");
        $this->line("   • سشن‌های ناسالم: {$unhealthy} ⚠️");
        
        if ($autoDisabled > 0) {
            $this->line("   • سشن‌های غیرفعال شده خودکار: {$autoDisabled} 🔴");
        }
        
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * دریافت Emoji بر اساس health score
     */
    private function getHealthStatusEmoji(int $healthScore): string
    {
        if ($healthScore >= 80) {
            return '✅'; // عالی
        } elseif ($healthScore >= 50) {
            return '⚠️ '; // متوسط
        } elseif ($healthScore >= 30) {
            return '🟡'; // ضعیف
        } else {
            return '❌'; // خراب
        }
    }
}
