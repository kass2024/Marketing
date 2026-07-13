<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_meta_connections', 'page_id')) {
                $table->string('page_id')->nullable()->after('ad_account_name');
            }
            if (! Schema::hasColumn('platform_meta_connections', 'page_name')) {
                $table->string('page_name')->nullable()->after('page_id');
            }
            if (! Schema::hasColumn('platform_meta_connections', 'instagram_business_account_id')) {
                $table->string('instagram_business_account_id')->nullable()->after('page_name');
            }
            if (! Schema::hasColumn('platform_meta_connections', 'whatsapp_phone_number')) {
                $table->string('whatsapp_phone_number')->nullable()->after('whatsapp_phone_number_id');
            }
            if (! Schema::hasColumn('platform_meta_connections', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('granted_permissions');
            }
        });

        Schema::table('meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_connections', 'business_id')) {
                $table->string('business_id')->nullable()->after('meta_user_id');
            }
            if (! Schema::hasColumn('meta_connections', 'ad_account_id')) {
                $table->string('ad_account_id')->nullable()->after('business_id');
            }
            if (! Schema::hasColumn('meta_connections', 'page_id')) {
                $table->string('page_id')->nullable()->after('ad_account_id');
            }
            if (! Schema::hasColumn('meta_connections', 'instagram_business_account_id')) {
                $table->string('instagram_business_account_id')->nullable()->after('page_id');
            }
            if (! Schema::hasColumn('meta_connections', 'whatsapp_business_id')) {
                $table->string('whatsapp_business_id')->nullable()->after('instagram_business_account_id');
            }
            if (! Schema::hasColumn('meta_connections', 'whatsapp_phone_number_id')) {
                $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_business_id');
            }
            if (! Schema::hasColumn('meta_connections', 'whatsapp_phone_number')) {
                $table->string('whatsapp_phone_number')->nullable()->after('whatsapp_phone_number_id');
            }
            if (! Schema::hasColumn('meta_connections', 'granted_permissions')) {
                $table->json('granted_permissions')->nullable()->after('token_expires_at');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'marketing_channel')) {
                $table->string('marketing_channel')->default('click_to_whatsapp')->after('objective');
            }
            if (! Schema::hasColumn('campaigns', 'wizard_state')) {
                $table->json('wizard_state')->nullable()->after('marketing_channel');
            }
            if (! Schema::hasColumn('campaigns', 'meta_effective_status')) {
                $table->string('meta_effective_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('campaigns', 'meta_review_feedback')) {
                $table->text('meta_review_feedback')->nullable()->after('meta_effective_status');
            }
            if (! Schema::hasColumn('campaigns', 'platform_meta_connection_id')) {
                $table->unsignedBigInteger('platform_meta_connection_id')->nullable()->after('client_id');
            }
        });

        Schema::table('ad_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_sets', 'destination_type')) {
                $table->string('destination_type')->nullable()->after('optimization_goal');
            }
            if (! Schema::hasColumn('ad_sets', 'meta_effective_status')) {
                $table->string('meta_effective_status')->nullable()->after('status');
            }
        });

        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'meta_effective_status')) {
                $table->string('meta_effective_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ads', 'meta_review_feedback')) {
                $table->text('meta_review_feedback')->nullable()->after('meta_effective_status');
            }
            if (! Schema::hasColumn('ads', 'meta_created_time')) {
                $table->timestamp('meta_created_time')->nullable()->after('meta_review_feedback');
            }
        });

        Schema::table('creatives', function (Blueprint $table) {
            if (! Schema::hasColumn('creatives', 'creative_format')) {
                $table->string('creative_format')->default('link')->after('type');
            }
            if (! Schema::hasColumn('creatives', 'description')) {
                $table->string('description')->nullable()->after('body');
            }
            if (! Schema::hasColumn('creatives', 'whatsapp_phone_number')) {
                $table->string('whatsapp_phone_number')->nullable()->after('destination_url');
            }
            if (! Schema::hasColumn('creatives', 'whatsapp_prefill_message')) {
                $table->text('whatsapp_prefill_message')->nullable()->after('whatsapp_phone_number');
            }
            if (! Schema::hasColumn('creatives', 'whatsapp_fallback_url')) {
                $table->string('whatsapp_fallback_url')->nullable()->after('whatsapp_prefill_message');
            }
            if (! Schema::hasColumn('creatives', 'page_id')) {
                $table->string('page_id')->nullable()->after('whatsapp_fallback_url');
            }
            if (! Schema::hasColumn('creatives', 'instagram_user_id')) {
                $table->string('instagram_user_id')->nullable()->after('page_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_webhook_events');
        Schema::dropIfExists('meta_api_logs');
    }
};
