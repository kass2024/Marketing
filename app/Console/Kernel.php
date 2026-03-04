<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Unread Messages Report
        |--------------------------------------------------------------------------
        */

        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();


        /*
        |--------------------------------------------------------------------------
        | META ADS AUTO SYNC
        |--------------------------------------------------------------------------
        | Keeps Meta data fresh automatically
        | Safe for production
        */

        // Sync Ad Accounts
        $schedule->command('meta:sync-accounts')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Sync Campaigns
        $schedule->command('meta:sync-campaigns')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Sync Ads
        $schedule->command('meta:sync-ads')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Sync Insights / Analytics
        $schedule->command('meta:sync-insights')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();


        /*
        |--------------------------------------------------------------------------
        | Optional Future Jobs
        |--------------------------------------------------------------------------
        */

        // $schedule->command('report:daily-summary')->dailyAt('18:00');
        // $schedule->command('queue:work --stop-when-empty')->everyMinute();

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