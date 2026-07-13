<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SafeDeploy extends Command
{
    protected $signature = 'deploy:safe
        {--skip-migrate : Skip database migrations}
        {--skip-clients : Skip client password + ad-account metadata sync}';

    protected $description = 'VPS deploy: auto-migrate, cache refresh — does not sync or change Meta ads';

    public function handle(): int
    {
        $this->info('Starting safe deploy (ads/campaigns/ad sets/creatives rows are not modified).');
        $this->newLine();

        if (! $this->option('skip-migrate')) {
            $exitCode = Artisan::call('migrate:auto', ['--force' => true, '--repair' => true]);
            $this->output->write(Artisan::output());

            if ($exitCode !== 0) {
                $this->error('Auto-migration failed.');

                return self::FAILURE;
            }
        } else {
            $this->warn('Skipped migrations.');
        }

        $this->newLine();
        $this->line('Ensuring admin login…');
        Artisan::call('users:ensure-admin');
        $this->output->write(Artisan::output());

        if (! $this->option('skip-clients')) {
            $this->newLine();
            $this->runOptionalCommand('business:sync-platform-ad-accounts', 'Syncing client ad-account metadata...');
            $this->runOptionalCommand('clients:set-default-password', 'Applying standard client login password...');
        } else {
            $this->warn('Skipped client metadata/password sync.');
        }

        $this->newLine();
        $this->line('Auto-syncing Meta platform connection + WhatsApp numbers...');
        Artisan::call('meta:auto-sync', ['--force' => true]);
        $this->output->write(Artisan::output());

        $this->newLine();
        $this->line('Clearing caches...');
        foreach (['config:clear', 'cache:clear', 'view:clear', 'route:clear'] as $command) {
            Artisan::call($command);
        }

        $this->info('Safe deploy finished.');
        $this->line('VPS cron (required): * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1');
        $this->line('Heavy Meta ads inventory sync (meta:sync-*) still runs on schedule; publish path uses live Graph.');

        return self::SUCCESS;
    }

    protected function runOptionalCommand(string $name, string $label): void
    {
        if (! $this->getApplication()->has($name)) {
            $this->warn("Skipped {$name} (command not registered).");

            return;
        }

        $this->line($label);
        Artisan::call($name);
        $this->output->write(Artisan::output());
    }
}
