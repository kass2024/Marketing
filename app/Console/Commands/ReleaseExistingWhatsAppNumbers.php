<?php

namespace App\Console\Commands;

use App\Services\Meta\WhatsAppBusinessAccountService;
use Illuminate\Console\Command;

class ReleaseExistingWhatsAppNumbers extends Command
{
    protected $signature = 'whatsapp:release-existing {phones* : Numbers already on the WhatsApp Business app}';

    protected $description = 'Remove unverified Cloud API rows without disconnecting WhatsApp Business';

    public function handle(WhatsAppBusinessAccountService $whatsapp): int
    {
        $phones = $this->argument('phones');
        if ($phones === []) {
            $this->error('Pass at least one phone number.');

            return self::FAILURE;
        }

        if (! $whatsapp->connection()) {
            $this->error('No Meta connection is configured.');

            return self::FAILURE;
        }

        foreach ($phones as $raw) {
            $result = $whatsapp->releaseExistingDisplayNumber((string) $raw);
            $this->line(trim((string) $raw).' → '.$result['status'].' — '.$result['message']);
        }

        return self::SUCCESS;
    }
}
