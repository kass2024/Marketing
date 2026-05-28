<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Services\InstagramDeliveryService;
use App\Support\TenantScope;
use Illuminate\Console\Command;
use Throwable;

class EnableInstagramDelivery extends Command
{
    protected $signature = 'meta:enable-instagram
                            {--dry-run : List counts only, do not call Meta}';

    protected $description = 'Update all existing campaigns, ad sets, creatives, and ads on Meta for Instagram delivery';

    public function handle(InstagramDeliveryService $instagram): int
    {
        $this->info('Enabling Instagram delivery for existing Meta objects…');

        if ($this->option('dry-run')) {
            $this->table(
                ['Entity', 'Synced with Meta'],
                [
                    ['Campaigns', (string) TenantScope::campaigns(Campaign::query())->whereNotNull('meta_id')->count()],
                    ['Ad sets', (string) TenantScope::adSets(AdSet::query())->whereNotNull('meta_id')->count()],
                    ['Creatives', (string) TenantScope::creatives(Creative::query())->whereNotNull('meta_id')->count()],
                    ['Ads', (string) TenantScope::ads(Ad::query())->whereNotNull('meta_ad_id')->count()],
                ]
            );
            $this->comment('Run without --dry-run to apply changes on Meta.');

            return Command::SUCCESS;
        }

        try {
            $instagram->assertInstagramConfigured();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->comment('Run: php artisan meta:verify-instagram');

            return Command::FAILURE;
        }

        $diag = $instagram->verify();
        $this->line('Using Instagram ID '.$diag['instagram_user_id'].' ('.$diag['source'].')');
        $this->newLine();

        $stats = $instagram->repairAll();

        $this->info($instagram->summaryMessage($stats));

        $this->table(
            ['Type', 'Updated', 'Skipped', 'Failed'],
            [
                ['Ad sets', $stats['adsets']['updated'], $stats['adsets']['skipped'], $stats['adsets']['failed']],
                ['Creatives', $stats['creatives']['updated'], $stats['creatives']['skipped'], $stats['creatives']['failed']],
                ['Ads', $stats['ads']['updated'], $stats['ads']['skipped'], $stats['ads']['failed']],
            ]
        );

        if ($stats['errors'] !== []) {
            $this->warn('Errors (first 10):');
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                $this->line(' - '.$error);
            }
        }

        $failed = $stats['adsets']['failed'] + $stats['creatives']['failed'] + $stats['ads']['failed'];

        return $failed > 0 && $stats['ads']['updated'] === 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
