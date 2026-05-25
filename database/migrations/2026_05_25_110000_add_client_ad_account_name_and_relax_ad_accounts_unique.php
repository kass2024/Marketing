<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'meta_ad_account_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('meta_ad_account_name')->nullable()->after('meta_ad_account_id');
            });
        }

        if (! Schema::hasTable('ad_accounts')) {
            return;
        }

        if (! Schema::hasColumn('ad_accounts', 'meta_id')) {
            Schema::table('ad_accounts', function (Blueprint $table) {
                $table->string('meta_id')->nullable()->after('ad_account_id');
            });
        }

        $indexes = $this->indexNames('ad_accounts');

        if ($indexes->contains('meta_id') && Schema::hasColumn('ad_accounts', 'meta_id')) {
            try {
                Schema::table('ad_accounts', function (Blueprint $table) {
                    $table->dropUnique('meta_id');
                });
            } catch (\Throwable $e) {
                // Index may already be dropped on a previous partial run.
            }
        }

        if (
            Schema::hasColumn('ad_accounts', 'meta_id')
            && ! $indexes->contains('ad_accounts_client_id_meta_id_unique')
            && ! $this->hasDuplicateAccountPairs()
        ) {
            try {
                Schema::table('ad_accounts', function (Blueprint $table) {
                    $table->unique(['client_id', 'meta_id'], 'ad_accounts_client_id_meta_id_unique');
                });
            } catch (\Throwable $e) {
                // Skip if production data prevents the composite unique index.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'meta_ad_account_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('meta_ad_account_name');
            });
        }

        if (! Schema::hasTable('ad_accounts')) {
            return;
        }

        $indexes = $this->indexNames('ad_accounts');

        if ($indexes->contains('ad_accounts_client_id_meta_id_unique')) {
            Schema::table('ad_accounts', function (Blueprint $table) {
                $table->dropUnique('ad_accounts_client_id_meta_id_unique');
            });
        }
    }

    protected function indexNames(string $table): \Illuminate\Support\Collection
    {
        $database = Schema::getConnection()->getDatabaseName();

        return collect(DB::select(
            'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $table]
        ))->pluck('name');
    }

    protected function hasDuplicateAccountPairs(): bool
    {
        if (! Schema::hasColumn('ad_accounts', 'meta_id')) {
            return false;
        }

        return DB::table('ad_accounts')
            ->select('client_id', 'meta_id')
            ->whereNotNull('meta_id')
            ->groupBy('client_id', 'meta_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
