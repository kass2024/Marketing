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
        | META ADS SYNC (FAST, SAFE)
        |--------------------------------------------------------------------------
        */
        $schedule->command('meta:sync-ads')
            ->everyMinute()
            ->withoutOverlapping(10) // 🔥 reduced lock time
            ->runInBackground()
            ->name('meta-sync')
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        /*
        |--------------------------------------------------------------------------
        | 🔥 BUDGET RESET (CRITICAL FIXED)
        |--------------------------------------------------------------------------
        */
        $schedule->command('ads:reset-daily-budget')
            ->everyMinute()
            ->withoutOverlapping(10) // 🔥 FIX: was blocking for 120s
            ->runInBackground()
            ->name('ads-budget-reset')
            ->appendOutputTo(storage_path('logs/ad-reset.log'))
            ->after(function () {
                Log::info('BUDGET_RESET_FINISHED');
            });


        /*
        |--------------------------------------------------------------------------
        | MESSAGING AUTOMATION
        |--------------------------------------------------------------------------
        */
        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->name('messaging-unread-report')
            ->appendOutputTo(storage_path('logs/scheduler.log'));


        /*
        |--------------------------------------------------------------------------
        | META MARKETING ENGINE
        |--------------------------------------------------------------------------
        */
        $schedule->command('meta:sync')
            ->everyThirtyMinutes()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->name('meta-sync-engine')
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        /*
        |--------------------------------------------------------------------------
        | AGENT ESCALATION MONITOR
        |--------------------------------------------------------------------------
        */
        $schedule->command('agents:monitor')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->name('agent-escalation-monitor')
            ->appendOutputTo(storage_path('logs/agent-monitor.log'));


        /*
        |--------------------------------------------------------------------------
        | QUEUE WORKER (IMPORTANT NOTE BELOW)
        |--------------------------------------------------------------------------
        */
        $schedule->command('queue:work --tries=3 --timeout=90')
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(10)
            ->name('chatbot-queue-worker')
            ->appendOutputTo(storage_path('logs/queue-worker.log'));


        /*
        |--------------------------------------------------------------------------
        | SYSTEM HEARTBEAT
        |--------------------------------------------------------------------------
        */
        $schedule->call(function () {

            Log::info('SYSTEM_SCHEDULER_HEARTBEAT', [
                'timestamp' => now()->toDateTimeString(),
                'environment' => app()->environment(),
            ]);

        })
        ->hourly()
        ->withoutOverlapping(10)
        ->name('scheduler-heartbeat');
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