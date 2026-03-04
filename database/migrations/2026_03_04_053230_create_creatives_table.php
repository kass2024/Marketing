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
        Schema::create('creatives', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Meta Identifiers
            |--------------------------------------------------------------------------
            */

            $table->string('meta_creative_id')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Creative Type
            |--------------------------------------------------------------------------
            | image | video | carousel | collection | dynamic
            */

            $table->string('type')->default('image')->index();

            /*
            |--------------------------------------------------------------------------
            | Core Creative Content
            |--------------------------------------------------------------------------
            */

            $table->string('name')->nullable();

            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('call_to_action')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Media Assets
            |--------------------------------------------------------------------------
            */

            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->json('carousel_items')->nullable();   // For multi-image ads

            /*
            |--------------------------------------------------------------------------
            | Destination
            |--------------------------------------------------------------------------
            */

            $table->string('destination_url')->nullable();
            $table->string('display_url')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Meta Raw Payload
            |--------------------------------------------------------------------------
            */

            $table->json('json_payload')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sync & Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creatives');
    }
};