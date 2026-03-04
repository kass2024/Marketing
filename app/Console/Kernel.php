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
        | Handles WhatsApp / Messenger unread message reporting.
        | Runs every 5 minutes and prevents overlapping executions.
        */

        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));


        /*
        |--------------------------------------------------------------------------
        | META MARKETING AUTO SYNC ENGINE
        |--------------------------------------------------------------------------
        | Keeps Meta Ads data updated for the SaaS dashboard.
        | Designed for safe background execution.
        */

        // Sync Meta Ad Accounts
        $schedule->command('meta:sync-accounts')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        // Sync Campaigns
        $schedule->command('meta:sync-campaigns')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        // Sync Ads
        $schedule->command('meta:sync-ads')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        // Sync Insights (analytics data)
        $schedule->command('meta:sync-insights')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/meta-sync.log'));


        /*
        |--------------------------------------------------------------------------
        | System Health Monitoring
        |--------------------------------------------------------------------------
        | Logs a heartbeat so you know the scheduler is alive.
        */

        $schedule->call(function () {

            Log::info('Scheduler heartbeat OK', [
                'timestamp' => now()->toDateTimeString()
            ]);

        })->hourly();


        /*
        |--------------------------------------------------------------------------
        | Optional Future Jobs
        |--------------------------------------------------------------------------
        */

        // Daily analytics summary
        // $schedule->command('report:daily-summary')
        //     ->dailyAt('18:00');

        // Queue worker health restart
        // $schedule->command('queue:restart')
        //     ->daily();

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