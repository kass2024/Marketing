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
        | Messaging Automation
        |--------------------------------------------------------------------------
        | WhatsApp / Messenger unread message reporting.
        | Runs every 5 minutes.
        */

        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->name('messaging-unread-report')
            ->appendOutputTo(storage_path('logs/scheduler.log'));


        /*
        |--------------------------------------------------------------------------
        | META MARKETING SYNC ENGINE
        |--------------------------------------------------------------------------
        | Unified Meta Ads synchronization engine.
        | Internally runs:
        | - sync accounts
        | - sync campaigns
        | - sync adsets
        | - sync ads
        | - sync insights
        */

        $schedule->command('meta:sync')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->name('meta-sync-engine')
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        /*
        |--------------------------------------------------------------------------
        | System Health Monitoring
        |--------------------------------------------------------------------------
        | Scheduler heartbeat to confirm cron is alive.
        */

        $schedule->call(function () {

            Log::info('SYSTEM_SCHEDULER_HEARTBEAT', [
                'timestamp' => now()->toDateTimeString(),
                'environment' => app()->environment()
            ]);

        })
        ->hourly()
        ->name('scheduler-heartbeat')
        ->withoutOverlapping();


        /*
        |--------------------------------------------------------------------------
        | Optional Future Jobs (Disabled)
        |--------------------------------------------------------------------------
        */

        // $schedule->command('report:daily-summary')
        //     ->dailyAt('18:00')
        //     ->withoutOverlapping();

        // $schedule->command('queue:restart')
        //     ->dailyAt('02:00');
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