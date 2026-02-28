<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_meta_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('connected_by')->constrained('users')->cascadeOnDelete();

            $table->string('facebook_user_id')->nullable();
            $table->string('business_id')->nullable();
            $table->string('business_name')->nullable();

            $table->string('ad_account_id')->nullable();
            $table->string('ad_account_name')->nullable();

            $table->string('whatsapp_business_id')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();

            $table->longText('access_token');
            $table->timestamp('token_expires_at')->nullable();

            $table->json('granted_permissions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_meta_connections');
    }
};