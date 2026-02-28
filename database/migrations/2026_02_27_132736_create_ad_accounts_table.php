<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('ad_account_id');
            $table->string('name')->nullable();
            $table->string('currency')->nullable();
            $table->string('account_status')->nullable();

            $table->timestamps();

            $table->unique(['client_id', 'ad_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};