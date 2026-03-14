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
        | META ADS SYNC
        |--------------------------------------------------------------------------
        | Synchronizes Meta Ads insights, spend, and metrics.
        | Updates dashboard data used by AJAX live refresh.
        */

    $schedule->command('meta:sync-ads')
    ->everyMinute()
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground()
    ->name('meta-sync')
    ->appendOutputTo(storage_path('logs/meta-sync.log'));

$schedule->command('ads:reset-daily-budget')
    ->everyMinute()
    ->withoutOverlapping(120)
    ->onOneServer()
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
        | WhatsApp / Messenger unread reporting.
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
        | META MARKETING ENGINE
        |--------------------------------------------------------------------------
        | Full Meta platform sync (campaigns, adsets, creatives, ads).
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
        | AGENT ESCALATION MONITOR
        |--------------------------------------------------------------------------
        | Ensures conversations are reassigned if agents do not respond.
        */

        $schedule->command('agents:monitor')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->name('agent-escalation-monitor')
            ->appendOutputTo(storage_path('logs/agent-monitor.log'));


        /*
        |--------------------------------------------------------------------------
        | CHATBOT QUEUE WORKER
        |--------------------------------------------------------------------------
        | Processes WhatsApp / Messenger chatbot jobs.
        */

        $schedule->command('queue:work --tries=3 --timeout=90')
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping()
            ->name('chatbot-queue-worker')
            ->appendOutputTo(storage_path('logs/queue-worker.log'));


        /*
        |--------------------------------------------------------------------------
        | SYSTEM HEALTH HEARTBEAT
        |--------------------------------------------------------------------------
        | Confirms scheduler is running.
        */

        $schedule->call(function () {

            Log::info('SYSTEM_SCHEDULER_HEARTBEAT', [
                'timestamp' => now()->toDateTimeString(),
                'environment' => app()->environment(),
            ]);

        })
        ->hourly()
        ->name('scheduler-heartbeat')
        ->withoutOverlapping();


        /*
        |--------------------------------------------------------------------------
        | OPTIONAL FUTURE JOBS
        |--------------------------------------------------------------------------
        */

        // $schedule->command('report:daily-summary')
        //     ->dailyAt('18:00');

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