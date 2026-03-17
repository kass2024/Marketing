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
        | 🔥 META ADS SYNC (CORE ENGINE)
        |--------------------------------------------------------------------------
        | Heavy → run every 5 min (NOT every minute)
        */

        $schedule->command('meta:sync-ads')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->name('meta-sync-ads')
            ->before(fn () => Log::info('META_SYNC_START', ['time' => now()]))
            ->after(fn () => Log::info('META_SYNC_DONE', ['time' => now()]))
            ->onFailure(fn () => Log::error('META_SYNC_FAILED', ['time' => now()]))
            ->appendOutputTo(storage_path('logs/meta-ads.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 CAMPAIGNS SYNC
        |--------------------------------------------------------------------------
        */

        $schedule->command('meta:sync-campaigns')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->name('meta-sync-campaigns')
            ->appendOutputTo(storage_path('logs/meta-campaigns.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 ACCOUNT STATUS SYNC (Billing / Health)
        |--------------------------------------------------------------------------
        */

        $schedule->command('meta:sync-accounts')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->name('meta-sync-accounts')
            ->appendOutputTo(storage_path('logs/meta-accounts.log'));



        /*
        |--------------------------------------------------------------------------
        | 💰 DAILY BUDGET RESET ENGINE
        |--------------------------------------------------------------------------
        | No need every minute → heavy DB writes
        */

        $schedule->command('ads:reset-daily-budget')
            ->everyFiveMinutes()
            ->withoutOverlapping()
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
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->name('messaging-unread-report')
            ->appendOutputTo(storage_path('logs/messaging.log'));



        /*
        |--------------------------------------------------------------------------
        | 🧠 AGENT MONITORING
        |--------------------------------------------------------------------------
        */

        $schedule->command('agents:monitor')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->name('agent-monitor')
            ->appendOutputTo(storage_path('logs/agent-monitor.log'));



        /*
        |--------------------------------------------------------------------------
        | ❤️ SYSTEM HEARTBEAT
        |--------------------------------------------------------------------------
        */

        $schedule->call(function () {

            Log::info('SYSTEM_HEARTBEAT', [
                'time' => now(),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'env' => app()->environment(),
            ]);

        })->hourly()->name('system-heartbeat');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}