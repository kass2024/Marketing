<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetClientDefaultPassword extends Command
{
    protected $signature = 'clients:set-default-password
        {--email= : Reset a single client by email}
        {--dry-run : Show what would change without updating}';

    protected $description = 'Set the standard client login password for all client accounts';

    public function handle(): int
    {
        $password = User::defaultClientPassword();
        $query = User::query()->where('role', User::ROLE_CLIENT);

        if ($email = $this->option('email')) {
            $query->whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->warn('No matching client accounts found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would reset {$clients->count()} client account(s) to the default password.");

            foreach ($clients as $client) {
                $this->line("  - {$client->email} ({$client->name})");
            }

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            $client->update(['password' => $password]);
            $this->line("Updated: {$client->email}");
        }

        $this->info("Reset {$clients->count()} client account(s). Default password: {$password}");

        return self::SUCCESS;
    }
}
