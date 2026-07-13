<?php

namespace App\Console\Commands;

use App\Services\Meta\WhatsAppBusinessAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class HealWhatsAppDirectoryCommand extends Command
{
    protected $signature = 'whatsapp:heal-directory {--sync : Also attempt a quiet Meta Graph sync after healing}';

    protected $description = 'Fix bad WABA ids and apply durable Meta BM names/phones so Business App accounts never stay blank';

    public function handle(WhatsAppBusinessAccountService $whatsapp): int
    {
        Cache::forget('meta_wa_rate_limited');
        foreach (['731320686199458', '2185384198950246', '2185384198958246'] as $id) {
            Cache::forget('meta_wa_waba_rl_'.$id);
            Cache::forget('meta_wa_no_phone_edge_'.$id);
        }

        $connection = $whatsapp->connection();
        if (! $connection) {
            $this->error('No platform Meta connection found.');

            return self::FAILURE;
        }

        $healed = $whatsapp->applyDurableWhatsAppDirectory($connection);
        $this->info('Durable directory applied.');
        $this->line('  Accounts: '.count($healed['accounts'] ?? []));
        $this->line('  Phones:   '.count($healed['phones'] ?? []));

        foreach ($healed['accounts'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if (! in_array($id, ['731320686199458', '2185384198958246'], true)) {
                continue;
            }
            $this->line("  • {$id} → ".($row['name'] ?? '?'));
        }

        foreach ($healed['phones'] ?? [] as $phone) {
            if (! is_array($phone)) {
                continue;
            }
            if ((string) ($phone['waba_id'] ?? '') !== '731320686199458') {
                continue;
            }
            $this->line('  • phone '.($phone['display_phone_number'] ?? '').' (id='.($phone['id'] ?? '').')');
        }

        if ($this->option('sync')) {
            $this->info('Quiet Graph sync…');
            try {
                $whatsapp->syncToConnection($connection);
                $whatsapp->applyDurableWhatsAppDirectory($connection);
                $this->info('Graph sync + re-heal done.');
            } catch (\Throwable $e) {
                $this->warn('Graph sync skipped/failed: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
