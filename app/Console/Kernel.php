<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {

        /*
        |--------------------------------------------------------------------------
        | 🔥 META ADS SYNC (SMART REAL-TIME ENGINE)
        |--------------------------------------------------------------------------
        */

        $schedule->command('meta:sync-ads')
            ->everyMinute()
            ->withoutOverlapping(3) // ⛔ prevent stacking
            ->runInBackground()
            ->name('meta-sync-ads')
            ->before(function () {
                Log::info('META_SYNC_START', [
                    'time' => now()->toDateTimeString()
                ]);
            })
            ->after(function () {
                Log::info('META_SYNC_FINISHED', [
                    'time' => now()->toDateTimeString()
                ]);
            })
            ->onFailure(function () {
                Log::error('META_SYNC_FAILED', [
                    'time' => now()->toDateTimeString()
                ]);
            })
            ->appendOutputTo(storage_path('logs/meta-ads.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 CAMPAIGNS (LESS FREQUENT)
        |--------------------------------------------------------------------------
        */

        $schedule->command('meta:sync-campaigns')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->name('meta-sync-campaigns')
            ->appendOutputTo(storage_path('logs/meta-campaigns.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 ACCOUNTS CHECK (IMPORTANT FOR BILLING STATUS)
        |--------------------------------------------------------------------------
        */

        $schedule->command('meta:sync-accounts')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->name('meta-sync-accounts')
            ->appendOutputTo(storage_path('logs/meta-accounts.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 DAILY BUDGET RESET (CRITICAL ENGINE)
        |--------------------------------------------------------------------------
        */

        $schedule->command('ads:reset-daily-budget')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->name('ads-budget-reset')
            ->before(fn () => Log::info('BUDGET_RESET_START'))
            ->after(fn () => Log::info('BUDGET_RESET_DONE'))
            ->onFailure(fn () => Log::error('BUDGET_RESET_FAILED'))
            ->appendOutputTo(storage_path('logs/ad-reset.log'));



        /*
        |--------------------------------------------------------------------------
        | 📩 MESSAGING AUTOMATION
        |--------------------------------------------------------------------------
        */

        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->name('messaging-unread-report')
            ->appendOutputTo(storage_path('logs/messaging.log'));



        /*
        |--------------------------------------------------------------------------
        | 🧠 AGENT ESCALATION MONITOR
        |--------------------------------------------------------------------------
        */

        $schedule->command('agents:monitor')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->name('agent-monitor')
            ->appendOutputTo(storage_path('logs/agent-monitor.log'));



        /*
        |--------------------------------------------------------------------------
        | ⚙️ QUEUE WORKER (TEMP - MOVE TO SUPERVISOR)
        |--------------------------------------------------------------------------
        */

        $schedule->command('queue:work --tries=3 --timeout=90 --sleep=3')
            ->everyMinute()
            ->withoutOverlapping(1)
            ->runInBackground()
            ->name('queue-worker')
            ->appendOutputTo(storage_path('logs/queue.log'));



        /*
        |--------------------------------------------------------------------------
        | ❤️ SYSTEM HEARTBEAT (HEALTH CHECK)
        |--------------------------------------------------------------------------
        */

        $schedule->call(function () {

            Log::info('SYSTEM_HEARTBEAT', [
                'time' => now()->toDateTimeString(),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'env' => app()->environment(),
            ]);

        })
        ->hourly()
        ->name('system-heartbeat');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}