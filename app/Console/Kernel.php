<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {

        /*
        |--------------------------------------------------------------------------
        | META ADS SYNC (CORE ENGINE)
        |--------------------------------------------------------------------------
        */

        // 🔥 ADS (REAL-TIME PERFORMANCE)
        $schedule->command('meta:sync-ads')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->name('meta-sync-ads')
            ->appendOutputTo(storage_path('logs/meta-ads.log'));

        // 🔥 CAMPAIGNS (LESS FREQUENT)
        $schedule->command('meta:sync-campaigns')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->name('meta-sync-campaigns')
            ->appendOutputTo(storage_path('logs/meta-campaigns.log'));

        // 🔥 ACCOUNTS (RARELY CHANGES)
        $schedule->command('meta:sync-accounts')
            ->hourly()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->name('meta-sync-accounts')
            ->appendOutputTo(storage_path('logs/meta-accounts.log'));



        /*
        |--------------------------------------------------------------------------
        | 🔥 BUDGET RESET (CRITICAL)
        |--------------------------------------------------------------------------
        */
        $schedule->command('ads:reset-daily-budget')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->name('ads-budget-reset')
            ->appendOutputTo(storage_path('logs/ad-reset.log'))
            ->onSuccess(function () {
                Log::info('BUDGET_RESET_SUCCESS');
            })
            ->onFailure(function () {
                Log::error('BUDGET_RESET_FAILED');
            });



        /*
        |--------------------------------------------------------------------------
        | MESSAGING AUTOMATION
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
        | AGENT ESCALATION MONITOR
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
        | QUEUE WORKER (⚠️ NOTE BELOW)
        |--------------------------------------------------------------------------
        */
        // ⚠️ RECOMMENDED: move this to Supervisor instead of scheduler
        $schedule->command('queue:work --tries=3 --timeout=90 --sleep=3')
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(1)
            ->name('queue-worker')
            ->appendOutputTo(storage_path('logs/queue.log'));



        /*
        |--------------------------------------------------------------------------
        | SYSTEM HEARTBEAT
        |--------------------------------------------------------------------------
        */
        $schedule->call(function () {

            Log::info('SYSTEM_HEARTBEAT', [
                'time' => now()->toDateTimeString(),
                'env' => app()->environment(),
            ]);

        })
        ->hourly()
        ->name('heartbeat');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}