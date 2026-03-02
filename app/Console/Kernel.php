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
        | Unread WhatsApp Messages Report
        |--------------------------------------------------------------------------
        | Sends admin all new unread incoming messages every 5 minutes.
        | - Prevents overlapping
        | - Runs in background
        | - Safe for production
        */

        $schedule->command('report:unread-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | Optional Future Jobs (Examples)
        |--------------------------------------------------------------------------
        |
        | $schedule->command('queue:work --stop-when-empty')->everyMinute();
        | $schedule->command('report:daily-summary')->dailyAt('18:00');
        |
        */
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}