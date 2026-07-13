<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'is_platform')) {
                $table->boolean('is_platform')->default(false)->after('subscription_status');
            }
        });

        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_meta_connections', 'client_id')) {
                $table->foreignId('client_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clients')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('platform_meta_connections', 'is_platform_default')) {
                $table->boolean('is_platform_default')->default(false)->after('is_active');
            }

            $table->index('whatsapp_phone_number_id');
            $table->index(['client_id', 'is_active']);
        });

        if (Schema::hasTable('meta_webhook_events') && ! Schema::hasColumn('meta_webhook_events', 'client_id')) {
            Schema::table('meta_webhook_events', function (Blueprint $table) {
                $table->foreignId('client_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clients')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('meta_api_logs') && ! Schema::hasColumn('meta_api_logs', 'client_id')) {
            Schema::table('meta_api_logs', function (Blueprint $table) {
                $table->foreignId('client_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clients')
                    ->nullOnDelete();
            });
        }

        Schema::table('creatives', function (Blueprint $table) {
            if (! Schema::hasColumn('creatives', 'campaign_id')) {
                $table->foreignId('campaign_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('campaigns')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('creatives', 'adset_id')) {
                $table->foreignId('adset_id')
                    ->nullable()
                    ->after('campaign_id')
                    ->constrained('ad_sets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            if (Schema::hasColumn('creatives', 'adset_id')) {
                $table->dropConstrainedForeignId('adset_id');
            }
            if (Schema::hasColumn('creatives', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
        });

        if (Schema::hasTable('meta_api_logs') && Schema::hasColumn('meta_api_logs', 'client_id')) {
            Schema::table('meta_api_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_id');
            });
        }

        if (Schema::hasTable('meta_webhook_events') && Schema::hasColumn('meta_webhook_events', 'client_id')) {
            Schema::table('meta_webhook_events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_id');
            });
        }

        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (Schema::hasColumn('platform_meta_connections', 'is_platform_default')) {
                $table->dropColumn('is_platform_default');
            }
            if (Schema::hasColumn('platform_meta_connections', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'is_platform')) {
                $table->dropColumn('is_platform');
            }
        });
    }
};
