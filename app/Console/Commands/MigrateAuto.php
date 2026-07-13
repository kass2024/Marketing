<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class MigrateAuto extends Command
{
    protected $signature = 'migrate:auto
        {--force : Run migrations even when APP_ENV is production}
        {--pretend : Show SQL without executing}
        {--no-fail : Exit successfully when the database is unreachable (composer hooks)}
        {--skip-check : Skip the database connectivity check}
        {--repair : Mark pending create_* migrations as ran when their tables already exist}';

    protected $description = 'Run pending migrations; optionally repair “table already exists” drift on VPS';

    public function handle(): int
    {
        if (! $this->option('skip-check')) {
            $this->line('Checking database connection...');
            $this->line('  Host: '.config('database.connections.'.config('database.default').'.host'));
            $this->line('  Database: '.config('database.connections.'.config('database.default').'.database'));

            try {
                DB::connection()->getPdo();
                $this->info('Database connection OK.');
            } catch (Throwable $e) {
                if ($this->option('no-fail')) {
                    $this->warn('Database unavailable — skipping migrations.');

                    return self::SUCCESS;
                }

                $this->error('Database connection failed: '.$e->getMessage());
                $this->newLine();
                $this->warn('Local: start MySQL/XAMPP, then run: php artisan migrate:auto');
                $this->warn('VPS: sudo systemctl start mysql && php artisan migrate:auto --force --repair');

                return self::FAILURE;
            }
        }

        // Always attempt a light repair before migrate on VPS (safe no-op when clean)
        $this->repairExistingCreateMigrations();

        $params = [];
        if ($this->option('pretend')) {
            $params['--pretend'] = true;
        }

        if ($this->option('force') || app()->environment('production', 'staging')) {
            $params['--force'] = true;
        }

        $this->line('Running pending migrations...');
        $exitCode = Artisan::call('migrate', $params);
        $output = trim(Artisan::output());

        if ($output !== '') {
            $this->output->write($output);
            $this->newLine();
        }

        if ($exitCode !== 0) {
            // One more repair + retry for “Base table or view already exists”
            if (str_contains($output, 'already exists') || str_contains($output, '42S01')) {
                $this->warn('Detected existing tables — repairing migration history and retrying…');
                $this->repairExistingCreateMigrations();
                $exitCode = Artisan::call('migrate', $params);
                $output = trim(Artisan::output());
                if ($output !== '') {
                    $this->output->write($output);
                    $this->newLine();
                }
            }
        }

        if ($exitCode !== 0) {
            $this->error('Migration failed.');

            return self::FAILURE;
        }

        $this->info('Migrations up to date.');

        return self::SUCCESS;
    }

    /**
     * When a create_* migration is pending but its table already exists
     * (common after partial deploys / renamed migration files), mark it ran.
     */
    protected function repairExistingCreateMigrations(): void
    {
        $ran = DB::table('migrations')->pluck('migration')->all();
        $batch = (int) (DB::table('migrations')->max('batch') ?? 0) + 1;
        $files = File::files(database_path('migrations'));
        $marked = 0;

        $tableGuess = [
            'create_ad_sets_table' => 'ad_sets',
            'create_creatives_table' => 'creatives',
            'create_ads_table' => 'ads',
            'create_users_table' => 'users',
            'create_clients_table' => 'clients',
            'create_campaigns_table' => 'campaigns',
            'create_jobs_table' => 'jobs',
            'create_failed_jobs_table' => 'failed_jobs',
            'create_templates_table' => 'templates',
            'create_chatbots_table' => 'chatbots',
            'create_chatbot_triggers_table' => 'chatbot_triggers',
            'create_chatbot_nodes_table' => 'chatbot_nodes',
            'create_conversations_table' => 'conversations',
            'create_messages_table' => 'messages',
            'create_conversation_states_table' => 'conversation_states',
            'create_meta_connections_table' => 'meta_connections',
            'create_ad_accounts_table' => 'ad_accounts',
            'create_platform_meta_connections_table' => 'platform_meta_connections',
            'create_personal_access_tokens_table' => 'personal_access_tokens',
        ];

        foreach ($files as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if (in_array($name, $ran, true)) {
                continue;
            }

            $table = null;
            foreach ($tableGuess as $needle => $tbl) {
                if (str_contains($name, $needle)) {
                    $table = $tbl;
                    break;
                }
            }

            // Generic: create_foo_bar_table → foo_bar
            if ($table === null && preg_match('/create_(.+)_table$/', $name, $m)) {
                $table = $m[1];
            }

            if ($table === null) {
                continue;
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $name,
                'batch' => $batch,
            ]);
            $this->line("Repaired: marked {$name} as ran ({$table} already exists).");
            $marked++;
        }

        if ($marked > 0) {
            $this->info("Repaired {$marked} migration(s).");
        } elseif ($this->option('repair')) {
            $this->line('No create-migration repairs needed.');
        }
    }
}
