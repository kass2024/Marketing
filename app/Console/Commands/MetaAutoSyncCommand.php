<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaAutoSyncService;
use Illuminate\Console\Command;

class MetaAutoSyncCommand extends Command
{
    protected $signature = 'meta:auto-sync {--force : Bypass throttle and sync immediately}';

    protected $description = 'Auto-sync platform Meta connection + WhatsApp numbers from .env and Graph API (VPS-safe)';

    public function handle(MetaAutoSyncService $sync): int
    {
        $result = $sync->sync($this->option('force'));

        if (! empty($result['skipped'])) {
            $this->comment('Skipped (throttled). Use --force to sync now.');

            return self::SUCCESS;
        }

        if (! empty($result['error']) && empty($result['synced'])) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synced connection #%s — %d WhatsApp number(s)%s',
            $result['connection_id'] ?? 'n/a',
            $result['phone_count'] ?? 0,
            ! empty($result['from_env']) ? ' (env token refreshed)' : ''
        ));

        return self::SUCCESS;
    }
}
