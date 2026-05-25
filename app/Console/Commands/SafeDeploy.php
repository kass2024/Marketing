<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SafeDeploy extends Command
{
    /**
     * Idempotent ALTER-only migrations safe for production (add nullable columns / indexes only).
     *
     * @var list<string>
     */
    protected array $safeAlterMigrations = [
        'database/migrations/2026_05_25_100000_add_facebook_page_tenant_columns.php',
        'database/migrations/2026_05_25_110000_add_client_ad_account_name_and_relax_ad_accounts_unique.php',
        'database/migrations/2026_05_26_100000_add_ads_budget_and_metrics_columns.php',
    ];

    protected $signature = 'deploy:safe
        {--full-migrate : Run all pending migrations, not just safe ALTER files}
        {--skip-migrate : Skip database migrations}
        {--skip-clients : Skip client password + ad-account metadata sync}';

    protected $description = 'Safe VPS deploy: run ALTER migrations and cache refresh without syncing or changing ads';

    public function handle(): int
    {
        $this->info('Starting safe deploy (ads/campaigns/ad sets/creatives rows are not modified).');
        $this->newLine();

        if (! $this->option('skip-migrate')) {
            foreach ($this->safeAlterMigrations as $path) {
                $this->line("Running safe migration: {$path}");
                $exitCode = Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => $path,
                ]);
                $output = trim(Artisan::output());

                if ($output !== '') {
                    $this->output->write($output);
                    $this->newLine();
                }

                if ($exitCode !== 0) {
                    $this->error("Migration failed: {$path}");

                    return self::FAILURE;
                }
            }

            if ($this->option('full-migrate')) {
                $this->line('Running all remaining pending migrations (--force)...');
                $exitCode = Artisan::call('migrate', ['--force' => true]);
                $output = trim(Artisan::output());

                if ($output !== '') {
                    $this->output->write($output);
                    $this->newLine();
                }

                if ($exitCode !== 0) {
                    $this->error('Full migration failed.');

                    return self::FAILURE;
                }
            } else {
                $this->info('Skipped full migrate (use --full-migrate only if you need other pending migrations).');
            }
        } else {
            $this->warn('Skipped migrations.');
        }

        if (! $this->option('skip-clients')) {
            $this->newLine();
            $this->line('Syncing client ad-account metadata (clients table only)...');
            Artisan::call('business:sync-platform-ad-accounts');
            $this->output->write(Artisan::output());

            $this->line('Applying standard client login password (users table only)...');
            Artisan::call('clients:set-default-password');
            $this->output->write(Artisan::output());
        } else {
            $this->warn('Skipped client metadata/password sync.');
        }

        $this->newLine();
        $this->line('Clearing caches...');
        foreach (['config:clear', 'cache:clear', 'view:clear', 'route:clear'] as $command) {
            Artisan::call($command);
        }

        $this->info('Safe deploy finished.');
        $this->line('Not run (by design): meta:sync-*, SyncMetaAds, SyncMetaCampaigns — existing ads on Meta are untouched.');

        return self::SUCCESS;
    }
}
