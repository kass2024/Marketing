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
        Schema::create('ads', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */

            $table->foreignId('adset_id')
                  ->constrained('ad_sets')
                  ->cascadeOnDelete();

            $table->foreignId('creative_id')
                  ->nullable()
                  ->constrained('creatives')
                  ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Meta Identifiers
            |--------------------------------------------------------------------------
            */

            $table->string('meta_ad_id')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Core Data
            |--------------------------------------------------------------------------
            */

            $table->string('name');
            $table->string('status')->default('PAUSED')->index();

            /*
            |--------------------------------------------------------------------------
            | Performance / Tracking
            |--------------------------------------------------------------------------
            */

            $table->decimal('budget', 12, 2)->nullable();
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('spend', 12, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | JSON Data (Meta Payloads)
            |--------------------------------------------------------------------------
            */

            $table->json('tracking_data')->nullable();
            $table->json('json_payload')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
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
        Schema::dropIfExists('ads');
    }
};