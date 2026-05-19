<?php

namespace App\Console\Commands;

use App\Models\PlatformMetaConnection;
use Illuminate\Console\Command;

class SyncWhatsAppTokenFromEnv extends Command
{
    protected $signature = 'whatsapp:sync-token-from-env
                            {--platform= : Platform meta connection ID (default: all matching phone)}';

    protected $description = 'Copy WHATSAPP_ACCESS_TOKEN from .env into platform_meta_connections (encrypted)';

    public function handle(): int
    {
        $token = trim((string) config('services.whatsapp.access_token'));

        if ($token === '') {
            $this->error('WHATSAPP_ACCESS_TOKEN is empty in .env / config.');

            return self::FAILURE;
        }

        $phoneId = config('services.whatsapp.phone_number_id');
        $platformId = $this->option('platform');

        $query = PlatformMetaConnection::query();

        if ($platformId) {
            $query->where('id', $platformId);
        } elseif ($phoneId) {
            $query->where('whatsapp_phone_number_id', $phoneId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->error('No platform_meta_connections row found. Pass --platform=8 or set WHATSAPP_PHONE_NUMBER_ID.');

            return self::FAILURE;
        }

        foreach ($connections as $connection) {
            $connection->storeAccessToken($token);
            $this->info("Updated platform #{$connection->id} (phone {$connection->whatsapp_phone_number_id}).");
        }

        $this->comment('Run: php artisan config:clear (on the server) if you just edited .env.');

        return self::SUCCESS;
    }
}
