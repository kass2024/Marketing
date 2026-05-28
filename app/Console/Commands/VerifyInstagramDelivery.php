<?php

namespace App\Console\Commands;

use App\Services\InstagramDeliveryService;
use Illuminate\Console\Command;

class VerifyInstagramDelivery extends Command
{
    protected $signature = 'meta:verify-instagram
                            {--repair : After verify, run meta:enable-instagram on all existing objects}';

    protected $description = 'Verify Page ↔ Instagram connection and readiness for FB+IG ad delivery';

    public function handle(InstagramDeliveryService $instagram): int
    {
        $report = $instagram->verify();

        $this->info('WABA Instagram delivery check (separate Meta account from xanderbot)');
        $this->table(
            ['Setting', 'Value'],
            [
                ['App', (string) ($report['app'] ?? 'WABA')],
                ['META_PAGE_ID', (string) ($report['page_id'] ?: '—')],
                ['META_AD_ACCOUNT_ID', (string) ($report['ad_account_id'] ?: '—')],
                ['Page shows connected IG', $report['page_connected'] ? 'Yes' : 'No'],
                ['Instagram user ID', (string) ($report['instagram_user_id'] ?: '—')],
                ['Resolved via', (string) ($report['source'] ?: '—')],
                ['.env META_INSTAGRAM_USER_ID', $report['env_fallback'] ? 'Set' : 'Not set'],
                ['Ready for ads', $report['ready'] ? 'YES' : 'NO'],
            ]
        );

        if (! empty($report['instagram_username'])) {
            $this->line('Instagram username: @'.$report['instagram_username']);
        }

        if (! empty($report['page_errors'])) {
            $this->warn('Page API notes:');
            foreach ($report['page_errors'] as $err) {
                $this->line('  '.$err);
            }
        }

        $this->newLine();
        $this->info('Synced entities in database');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Campaigns', (string) $report['entities']['campaigns']],
                ['Ad sets', (string) $report['entities']['adsets']],
                ['Creatives', (string) $report['entities']['creatives']],
                ['Ads', (string) $report['entities']['ads']],
            ]
        );

        $this->newLine();
        $this->info('New objects (after deploy)');
        foreach ($report['new_ads'] as $key => $value) {
            $this->line("  • {$key}: {$value}");
        }

        if (! $report['ready']) {
            $this->newLine();
            $this->error('Instagram is not configured for the API token yet.');
            foreach ($report['hints'] ?? [] as $hint) {
                $this->line('  → '.$hint);
            }

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('Instagram connection OK. Creatives and ad sets can deliver to IG.');

        if ($this->option('repair')) {
            $this->newLine();
            $this->call('meta:enable-instagram');
        } else {
            $this->comment('Run: php artisan meta:enable-instagram  (updates all existing ad sets, creatives, ads on Meta)');
            $this->comment('Or:  php artisan meta:verify-instagram --repair');
        }

        return Command::SUCCESS;
    }
}
