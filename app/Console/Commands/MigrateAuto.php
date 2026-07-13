<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateAuto extends Command
{
    protected $signature = 'migrate:auto
        {--force : Run migrations even when APP_ENV is production}
        {--pretend : Show SQL without executing}
        {--no-fail : Exit successfully when the database is unreachable (composer hooks)}
        {--skip-check : Skip the database connectivity check}';

    protected $description = 'Run all pending migrations (safe idempotent ALTER migrations for local + VPS)';

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
                $this->warn('VPS: sudo systemctl start mysql && php artisan migrate:auto --force');

                return self::FAILURE;
            }
        }

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
            $this->error('Migration failed.');

            return self::FAILURE;
        }

        $this->info('Migrations up to date.');

        return self::SUCCESS;
    }
}
