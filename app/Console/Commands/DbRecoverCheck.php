<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DbRecoverCheck extends Command
{
    protected $signature = 'db:recover-check';

    protected $description = 'Test MySQL connectivity and print recovery hints';

    public function handle(): int
    {
        $this->line('Checking database connection...');
        $this->line('Host: '.config('database.connections.mysql.host'));
        $this->line('Port: '.config('database.connections.mysql.port'));
        $this->line('Database: '.config('database.connections.mysql.database'));
        $this->line('User: '.config('database.connections.mysql.username'));

        try {
            DB::connection()->getPdo();
            $this->info('Database connection OK.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Database connection failed.');
            $this->line($e->getMessage());
            $this->newLine();
            $this->warn('Recovery steps on the VPS:');
            $this->line('  sudo systemctl start mysql   # or: mariadb');
            $this->line('  sudo systemctl status mysql');
            $this->line('  cd /var/www/Marketing && php artisan config:clear');
            $this->line('  php artisan db:recover-check');
            $this->newLine();
            $this->warn('Verify .env values: DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD');

            return self::FAILURE;
        }
    }
}
