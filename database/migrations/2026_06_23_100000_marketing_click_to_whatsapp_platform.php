<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make marketing creatives alters safe on VPS schemas that diverge from local
 * (e.g. missing `type` — never use after('type')).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meta_api_logs')) {
            Schema::create('meta_api_logs', function (Blueprint $table) {
                $table->id();
                $table->string('method', 10)->index();
                $table->string('endpoint')->index();
                $table->string('resource_type')->nullable()->index();
                $table->unsignedBigInteger('resource_id')->nullable()->index();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->boolean('success')->default(false)->index();
                $table->boolean('is_retryable')->default(false);
                $table->unsignedInteger('duration_ms')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_body')->nullable();
                $table->string('error_message')->nullable();
                $table->unsignedInteger('meta_error_code')->nullable();
                $table->string('meta_error_type')->nullable();
                $table->string('correlation_id', 36)->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meta_webhook_events')) {
            Schema::create('meta_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('object_type')->nullable()->index();
                $table->string('event_type')->nullable()->index();
                $table->string('field')->nullable();
                $table->string('entry_id')->nullable()->index();
                $table->string('phone_number_id')->nullable()->index();
                $table->string('ad_id')->nullable()->index();
                $table->string('campaign_id')->nullable()->index();
                $table->string('signature_valid')->default('unknown');
                $table->json('payload');
                $table->string('correlation_id', 36)->nullable()->index();
                $table->boolean('processed')->default(false)->index();
                $table->text('processing_notes')->nullable();
                $table->timestamps();
            });
        }

        $this->addMissing('platform_meta_connections', [
            'page_id' => fn (Blueprint $t) => $t->string('page_id')->nullable(),
            'page_name' => fn (Blueprint $t) => $t->string('page_name')->nullable(),
            'instagram_business_account_id' => fn (Blueprint $t) => $t->string('instagram_business_account_id')->nullable(),
            'whatsapp_phone_number' => fn (Blueprint $t) => $t->string('whatsapp_phone_number')->nullable(),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
        ]);

        $this->addMissing('meta_connections', [
            'business_id' => fn (Blueprint $t) => $t->string('business_id')->nullable(),
            'ad_account_id' => fn (Blueprint $t) => $t->string('ad_account_id')->nullable(),
            'page_id' => fn (Blueprint $t) => $t->string('page_id')->nullable(),
            'instagram_business_account_id' => fn (Blueprint $t) => $t->string('instagram_business_account_id')->nullable(),
            'whatsapp_business_id' => fn (Blueprint $t) => $t->string('whatsapp_business_id')->nullable(),
            'whatsapp_phone_number_id' => fn (Blueprint $t) => $t->string('whatsapp_phone_number_id')->nullable(),
            'whatsapp_phone_number' => fn (Blueprint $t) => $t->string('whatsapp_phone_number')->nullable(),
            'granted_permissions' => fn (Blueprint $t) => $t->json('granted_permissions')->nullable(),
        ]);

        $this->addMissing('campaigns', [
            'marketing_channel' => fn (Blueprint $t) => $t->string('marketing_channel')->default('click_to_whatsapp'),
            'wizard_state' => fn (Blueprint $t) => $t->json('wizard_state')->nullable(),
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
            'meta_review_feedback' => fn (Blueprint $t) => $t->text('meta_review_feedback')->nullable(),
            'platform_meta_connection_id' => fn (Blueprint $t) => $t->unsignedBigInteger('platform_meta_connection_id')->nullable(),
        ]);

        $this->addMissing('ad_sets', [
            'destination_type' => fn (Blueprint $t) => $t->string('destination_type')->nullable(),
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
        ]);

        $this->addMissing('ads', [
            'meta_effective_status' => fn (Blueprint $t) => $t->string('meta_effective_status')->nullable(),
            'meta_review_feedback' => fn (Blueprint $t) => $t->text('meta_review_feedback')->nullable(),
            'meta_created_time' => fn (Blueprint $t) => $t->timestamp('meta_created_time')->nullable(),
        ]);

        $this->addMissing('creatives', [
            'type' => fn (Blueprint $t) => $t->string('type')->nullable()->default('image'),
            'creative_format' => fn (Blueprint $t) => $t->string('creative_format')->default('link'),
            'description' => fn (Blueprint $t) => $t->string('description')->nullable(),
            'whatsapp_phone_number' => fn (Blueprint $t) => $t->string('whatsapp_phone_number')->nullable(),
            'whatsapp_prefill_message' => fn (Blueprint $t) => $t->text('whatsapp_prefill_message')->nullable(),
            'whatsapp_fallback_url' => fn (Blueprint $t) => $t->string('whatsapp_fallback_url')->nullable(),
            'whatsapp_chat_url' => fn (Blueprint $t) => $t->string('whatsapp_chat_url')->nullable(),
            'page_id' => fn (Blueprint $t) => $t->string('page_id')->nullable(),
            'instagram_user_id' => fn (Blueprint $t) => $t->string('instagram_user_id')->nullable(),
            'campaign_id' => fn (Blueprint $t) => $t->unsignedBigInteger('campaign_id')->nullable(),
            'adset_id' => fn (Blueprint $t) => $t->unsignedBigInteger('adset_id')->nullable(),
            'image_hash' => fn (Blueprint $t) => $t->string('image_hash')->nullable(),
            'headline' => fn (Blueprint $t) => $t->string('headline')->nullable(),
            'status' => fn (Blueprint $t) => $t->string('status')->default('ACTIVE'),
            'meta_id' => fn (Blueprint $t) => $t->string('meta_id')->nullable(),
        ]);
    }

    /**
     * @param  array<string, callable(Blueprint): void>  $columns
     */
    protected function addMissing(string $table, array $columns): void
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
        Schema::dropIfExists('meta_webhook_events');
        Schema::dropIfExists('meta_api_logs');
    }
};
