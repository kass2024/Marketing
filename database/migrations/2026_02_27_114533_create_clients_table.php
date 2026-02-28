<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Relationship to users table
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->string('company_name');
            $table->string('business_email')->nullable();
            $table->string('phone')->nullable();

            $table->enum('subscription_plan', ['free', 'pro', 'enterprise'])
                  ->default('free');

            $table->enum('subscription_status', ['active', 'inactive', 'cancelled'])
                  ->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};