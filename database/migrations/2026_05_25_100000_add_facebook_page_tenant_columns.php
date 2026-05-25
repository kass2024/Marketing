<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (! Schema::hasColumn('clients', 'meta_page_id')) {
                    $table->string('meta_page_id')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('clients', 'meta_page_name')) {
                    $table->string('meta_page_name')->nullable()->after('meta_page_id');
                }
                if (! Schema::hasColumn('clients', 'meta_ad_account_id')) {
                    $table->string('meta_ad_account_id')->nullable()->after('meta_page_name');
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'meta_page_id')) {
                    $table->string('meta_page_id')->nullable()->after('client_id');
                    $table->index('meta_page_id');
                }
            });
        }

        if (Schema::hasTable('meta_connections')) {
            Schema::table('meta_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('meta_connections', 'meta_page_id')) {
                    $table->string('meta_page_id')->nullable()->after('token_expires_at');
                }
                if (! Schema::hasColumn('meta_connections', 'meta_page_name')) {
                    $table->string('meta_page_name')->nullable()->after('meta_page_id');
                }
                if (! Schema::hasColumn('meta_connections', 'meta_ad_account_id')) {
                    $table->string('meta_ad_account_id')->nullable()->after('meta_page_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                foreach (['meta_page_id', 'meta_page_name', 'meta_ad_account_id'] as $column) {
                    if (Schema::hasColumn('clients', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (Schema::hasColumn('campaigns', 'meta_page_id')) {
                    $table->dropIndex(['meta_page_id']);
                    $table->dropColumn('meta_page_id');
                }
            });
        }

        if (Schema::hasTable('meta_connections')) {
            Schema::table('meta_connections', function (Blueprint $table) {
                foreach (['meta_page_id', 'meta_page_name', 'meta_ad_account_id'] as $column) {
                    if (Schema::hasColumn('meta_connections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
