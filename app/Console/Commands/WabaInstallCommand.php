<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformBootstrapService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class WabaInstallCommand extends Command
{
    protected $signature = 'waba:install
                            {--fresh : Drop all tables and re-migrate}
                            {--force : Skip confirmation when using --fresh}
                            {--no-seed : Skip database seeders}
                            {--skip-platform : Skip syncing .env platform credentials}';

    protected $description = 'Fresh install WABA: migrate database, seed tenants, bootstrap main account from .env';

    public function handle(PlatformBootstrapService $bootstrap): int
    {
        $this->info('WABA multi-tenant install starting…');

        if ($this->option('fresh')) {
            if (! $this->option('force') && ! $this->confirm('This will DROP ALL TABLES in '.config('database.connections.mysql.database').'. Continue?', true)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }

            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());
        }

        if (! $this->option('no-seed')) {
            $this->info('Seeding platform admin and demo tenant…');
            $this->call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => PlatformSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => TenantSeeder::class, '--force' => true]);
        }

        if (! $this->option('skip-platform')) {
            $this->info('Bootstrapping main platform account from .env…');
            $connection = $bootstrap->syncFromEnv();

            if ($connection) {
                $this->info("Platform connection #{$connection->id} synced (phone: {$connection->whatsapp_phone_number_id}).");
            } else {
                $this->warn('No META_SYSTEM_USER_TOKEN / WHATSAPP_PHONE_NUMBER_ID in .env — platform connection skipped.');
            }
        }

        $this->info('Done. Login: infos@visaconsultantcanada.com / VisaCanada2026!');

        return self::SUCCESS;
    }
}
