<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureWebhookLogFileExists();
    }

    /**
     * Create storage/logs/webhook.log on deploy so `tail -f` works before the first event.
     */
    private function ensureWebhookLogFileExists(): void
    {
        $path = storage_path('logs/webhook.log');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (! is_file($path) && is_writable($dir)) {
            @file_put_contents($path, '');
        }
    }
}
