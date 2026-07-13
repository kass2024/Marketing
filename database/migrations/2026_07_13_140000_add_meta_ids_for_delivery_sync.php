<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align local schema with Meta delivery sync / Ad Studio publish.
 * Without campaigns.meta_id (and ad set / creative Meta IDs), published ads
 * cannot be synced or activated from the app into Ads Manager delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'meta_id')) {
                $table->string('meta_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('campaigns', 'ad_account_id')) {
                $table->unsignedBigInteger('ad_account_id')->nullable()->index()->after('client_id');
            }
            if (! Schema::hasColumn('campaigns', 'daily_budget')) {
                $table->decimal('daily_budget', 12, 2)->nullable()->after('budget');
            }
        });

        // Ad sets: code uses meta_id; table historically used meta_adset_id
        Schema::table('ad_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_sets', 'meta_id')) {
                $table->string('meta_id')->nullable()->index()->after('campaign_id');
            }
        });

        if (Schema::hasColumn('ad_sets', 'meta_adset_id') && Schema::hasColumn('ad_sets', 'meta_id')) {
            DB::table('ad_sets')
                ->whereNull('meta_id')
                ->whereNotNull('meta_adset_id')
                ->update([
                    'meta_id' => DB::raw('meta_adset_id'),
                ]);
        }

        // Creatives: code uses meta_id; table historically used meta_creative_id
        if (Schema::hasTable('creatives')) {
            Schema::table('creatives', function (Blueprint $table) {
                if (! Schema::hasColumn('creatives', 'meta_id')) {
                    $table->string('meta_id')->nullable()->index()->after('adset_id');
                }
                if (! Schema::hasColumn('creatives', 'image_hash')) {
                    $table->string('image_hash')->nullable()->after('image_url');
                }
                if (! Schema::hasColumn('creatives', 'headline')) {
                    $table->string('headline')->nullable()->after('name');
                }
                if (! Schema::hasColumn('creatives', 'status')) {
                    $table->string('status')->default('ACTIVE')->after('is_active');
                }
            });

            if (Schema::hasColumn('creatives', 'meta_creative_id') && Schema::hasColumn('creatives', 'meta_id')) {
                DB::table('creatives')
                    ->whereNull('meta_id')
                    ->whereNotNull('meta_creative_id')
                    ->update([
                        'meta_id' => DB::raw('meta_creative_id'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'meta_id')) {
                $table->dropColumn('meta_id');
            }
            if (Schema::hasColumn('campaigns', 'ad_account_id')) {
                $table->dropColumn('ad_account_id');
            }
            if (Schema::hasColumn('campaigns', 'daily_budget')) {
                $table->dropColumn('daily_budget');
            }
        });

        Schema::table('ad_sets', function (Blueprint $table) {
            if (Schema::hasColumn('ad_sets', 'meta_id')) {
                $table->dropColumn('meta_id');
            }
        });

        if (Schema::hasTable('creatives')) {
            Schema::table('creatives', function (Blueprint $table) {
                foreach (['meta_id', 'image_hash', 'headline', 'status'] as $col) {
                    if (Schema::hasColumn('creatives', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
