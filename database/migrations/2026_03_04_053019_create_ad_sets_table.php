<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ad_sets', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('campaign_id')
                  ->constrained('campaigns')
                  ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Meta Identifiers
            |--------------------------------------------------------------------------
            */

            $table->string('meta_adset_id')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Core Data
            |--------------------------------------------------------------------------
            */

            $table->string('name');
            $table->string('status')->default('PAUSED')->index();

            $table->integer('daily_budget')->nullable();
            $table->integer('lifetime_budget')->nullable();

            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Targeting (Geo, Age, Gender, Interests, etc.)
            |--------------------------------------------------------------------------
            */

            $table->json('targeting')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optimization
            |--------------------------------------------------------------------------
            */

            $table->string('optimization_goal')->nullable();
            $table->string('billing_event')->nullable();
            $table->string('bid_strategy')->nullable();
            $table->decimal('bid_amount', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Performance Metrics
            |--------------------------------------------------------------------------
            */

            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('spend', 12, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Sync Tracking
            |--------------------------------------------------------------------------
            */

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_sets');
    }
};