<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Final alignment pass: ensure VPS has the same critical columns as local
 * without fragile after() positioning (MySQL fails when the "after" column is missing).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensure('campaigns', [
            'meta_id' => fn (Blueprint $t) => $t->string('meta_id')->nullable()->index(),
            'ad_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('ad_account_id')->nullable()->index(),
            'daily_budget' => fn (Blueprint $t) => $t->decimal('daily_budget', 12, 2)->nullable(),
            'marketing_channel' => fn (Blueprint $t) => $t->string('marketing_channel')->default('click_to_whatsapp'),
            'wizard_state' => fn (Blueprint $t) => $t->json('wizard_state')->nullable(),
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
            'meta_review_feedback' => fn (Blueprint $t) => $t->text('meta_review_feedback')->nullable(),
            'platform_meta_connection_id' => fn (Blueprint $t) => $t->unsignedBigInteger('platform_meta_connection_id')->nullable(),
            'meta_page_id' => fn (Blueprint $t) => $t->string('meta_page_id')->nullable(),
        ]);

        // Widen status if still enum-only (best-effort)
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'status')) {
            try {
                DB::statement("ALTER TABLE campaigns MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");
            } catch (\Throwable) {
                // ignore if already string / no permission nuance
            }
        }

        $this->ensure('ad_sets', [
            'meta_id' => fn (Blueprint $t) => $t->string('meta_id')->nullable()->index(),
            'meta_adset_id' => fn (Blueprint $t) => $t->string('meta_adset_id')->nullable()->index(),
            'destination_type' => fn (Blueprint $t) => $t->string('destination_type')->nullable(),
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
        ]);

        if (Schema::hasColumn('ad_sets', 'meta_adset_id') && Schema::hasColumn('ad_sets', 'meta_id')) {
            DB::table('ad_sets')->whereNull('meta_id')->whereNotNull('meta_adset_id')
                ->update(['meta_id' => DB::raw('meta_adset_id')]);
        }

        $this->ensure('ads', [
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
            'meta_review_feedback' => fn (Blueprint $t) => $t->text('meta_review_feedback')->nullable(),
            'meta_created_time' => fn (Blueprint $t) => $t->timestamp('meta_created_time')->nullable(),
            'daily_budget' => fn (Blueprint $t) => $t->decimal('daily_budget', 12, 2)->nullable(),
            'daily_spend' => fn (Blueprint $t) => $t->decimal('daily_spend', 12, 2)->nullable(),
        ]);

        $this->ensure('creatives', [
            'type' => fn (Blueprint $t) => $t->string('type')->nullable()->default('image'),
            'creative_format' => fn (Blueprint $t) => $t->string('creative_format')->default('link'),
            'meta_id' => fn (Blueprint $t) => $t->string('meta_id')->nullable()->index(),
            'meta_creative_id' => fn (Blueprint $t) => $t->string('meta_creative_id')->nullable()->index(),
            'campaign_id' => fn (Blueprint $t) => $t->unsignedBigInteger('campaign_id')->nullable()->index(),
            'adset_id' => fn (Blueprint $t) => $t->unsignedBigInteger('adset_id')->nullable()->index(),
            'headline' => fn (Blueprint $t) => $t->string('headline')->nullable(),
            'description' => fn (Blueprint $t) => $t->string('description')->nullable(),
            'image_hash' => fn (Blueprint $t) => $t->string('image_hash')->nullable(),
            'status' => fn (Blueprint $t) => $t->string('status')->default('ACTIVE'),
            'whatsapp_phone_number' => fn (Blueprint $t) => $t->string('whatsapp_phone_number')->nullable(),
            'whatsapp_prefill_message' => fn (Blueprint $t) => $t->text('whatsapp_prefill_message')->nullable(),
            'whatsapp_fallback_url' => fn (Blueprint $t) => $t->string('whatsapp_fallback_url')->nullable(),
            'whatsapp_chat_url' => fn (Blueprint $t) => $t->string('whatsapp_chat_url', 2048)->nullable(),
            'page_id' => fn (Blueprint $t) => $t->string('page_id')->nullable(),
            'instagram_user_id' => fn (Blueprint $t) => $t->string('instagram_user_id')->nullable(),
            'service_name' => fn (Blueprint $t) => $t->string('service_name')->nullable(),
            'campaign_goal' => fn (Blueprint $t) => $t->string('campaign_goal')->nullable(),
            'target_audience' => fn (Blueprint $t) => $t->string('target_audience')->nullable(),
            'pain_point' => fn (Blueprint $t) => $t->text('pain_point')->nullable(),
            'main_benefit' => fn (Blueprint $t) => $t->text('main_benefit')->nullable(),
            'offer_discount' => fn (Blueprint $t) => $t->string('offer_discount')->nullable(),
            'template_key' => fn (Blueprint $t) => $t->string('template_key')->nullable(),
            'ab_variant' => fn (Blueprint $t) => $t->string('ab_variant')->nullable(),
            'creative_group_id' => fn (Blueprint $t) => $t->uuid('creative_group_id')->nullable(),
            'placements' => fn (Blueprint $t) => $t->json('placements')->nullable(),
            'builder_inputs' => fn (Blueprint $t) => $t->json('builder_inputs')->nullable(),
            'is_reusable' => fn (Blueprint $t) => $t->boolean('is_reusable')->default(true),
        ]);

        if (Schema::hasColumn('creatives', 'meta_creative_id') && Schema::hasColumn('creatives', 'meta_id')) {
            DB::table('creatives')->whereNull('meta_id')->whereNotNull('meta_creative_id')
                ->update(['meta_id' => DB::raw('meta_creative_id')]);
        }

        $this->ensure('platform_meta_connections', [
            'linked_waba_ids' => fn (Blueprint $t) => $t->json('linked_waba_ids')->nullable(),
            'client_id' => fn (Blueprint $t) => $t->unsignedBigInteger('client_id')->nullable()->index(),
            'is_platform_default' => fn (Blueprint $t) => $t->boolean('is_platform_default')->default(false),
            'page_id' => fn (Blueprint $t) => $t->string('page_id')->nullable(),
            'page_name' => fn (Blueprint $t) => $t->string('page_name')->nullable(),
            'instagram_business_account_id' => fn (Blueprint $t) => $t->string('instagram_business_account_id')->nullable(),
            'whatsapp_phone_number' => fn (Blueprint $t) => $t->string('whatsapp_phone_number')->nullable(),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
        ]);
    }

    /**
     * @param  array<string, callable(Blueprint): void>  $columns
     */
    protected function ensure(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $name => $callback) {
                if (! Schema::hasColumn($table, $name)) {
                    $callback($blueprint);
                }
            }
        });
    }

    public function down(): void
    {
        // non-destructive alignment — no rollback
    }
};
